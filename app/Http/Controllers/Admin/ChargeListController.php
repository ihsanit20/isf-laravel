<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewChargeRequest;
use App\Models\Charge;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
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
                    'settledBy:id,name',
                ])
                ->latest('effective_at')
                ->latest('id')
                ->get()
                ->map(fn(Charge $charge): array => [
                    'id' => $charge->id,
                    'amount' => $charge->amount,
                    'status' => $charge->status,
                    'effective_at' => $charge->effective_at?->format('d M Y, h:i A'),
                    'settled_at' => $charge->settled_at?->format('d M Y, h:i A'),
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
                    'settled_by' => $charge->settledBy?->name,
                ])
                ->values(),
        ]);
    }

    public function review(ReviewChargeRequest $request, Charge $charge): RedirectResponse
    {
        if ($charge->status !== Charge::STATUS_PENDING) {
            return back()->withErrors([
                'status' => 'Only pending charges can be reviewed.',
            ]);
        }

        /** @var User|null $user */
        $user = $request->user();
        $status = $request->string('status')->toString();

        $charge->update([
            'status' => $status,
            'settled_at' => $status === Charge::STATUS_POSTED ? now() : null,
            'settled_by_user_id' => $status === Charge::STATUS_POSTED ? $user?->id : null,
        ]);

        return to_route('admin.charges.index');
    }
}
