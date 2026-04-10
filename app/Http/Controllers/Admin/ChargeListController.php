<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelChargeRequest;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChargeListController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Charges', [
            'charges' => Charge::query()
                ->with([
                    'category:id,code,title',
                    'member:id,full_name,managed_by_user_id',
                    'member.manager:id,name,email',
                    'allocations',
                ])
                ->latest('effective_at')
                ->latest('id')
                ->get()
                ->map(fn(Charge $charge): array => [
                    'id' => $charge->id,
                    'amount' => $charge->amount,
                    'status' => $charge->status,
                    'effective_at' => $charge->effective_at?->format('d M Y, h:i A'),
                    'category' => [
                        'code' => $charge->category?->code,
                        'title' => $charge->category?->title,
                    ],
                    'member' => [
                        'id' => $charge->member?->id,
                        'full_name' => $charge->member?->full_name,
                        'manager_name' => $charge->member?->manager?->name,
                        'manager_email' => $charge->member?->manager?->email,
                    ],
                    'allocated_amount' => (int) $charge->allocations->whereNull('reversed_at')->sum('amount'),
                ])
                ->values(),
        ]);
    }

    public function cancel(CancelChargeRequest $request, Charge $charge): RedirectResponse
    {
        if ($charge->status === Charge::STATUS_CANCELLED) {
            return back()->withErrors([
                'charge' => 'This charge has already been cancelled.',
            ]);
        }

        DB::transaction(function () use ($request, $charge): void {
            $charge->loadMissing(['category', 'member', 'allocations']);

            if ($charge->status === Charge::STATUS_POSTED) {
                ChargeAllocation::query()
                    ->where('charge_id', $charge->id)
                    ->whereNull('reversed_at')
                    ->update([
                        'reversed_at' => now(),
                        'reversed_by_user_id' => $request->user()?->id,
                    ]);
            }

            $charge->update([
                'status' => Charge::STATUS_CANCELLED,
            ]);

            if ($charge->category?->code === ChargeCategory::CODE_REGISTRATION_FEE) {
                $hasSettledRegistrationCharge = Charge::query()
                    ->where('member_id', $charge->member_id)
                    ->where('charge_category_id', $charge->charge_category_id)
                    ->where('id', '!=', $charge->id)
                    ->whereIn('status', [Charge::STATUS_POSTED, Charge::STATUS_WAIVED])
                    ->exists();

                if (! $hasSettledRegistrationCharge) {
                    $charge->member?->update([
                        'activated_at' => null,
                    ]);
                }
            }
        });

        return to_route('admin.charges.index');
    }
}
