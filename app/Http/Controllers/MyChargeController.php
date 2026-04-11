<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyChargeController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $charges = Charge::query()
            ->with([
                'category:id,title,code',
                'member:id,full_name,managed_by_user_id',
                'allocations',
            ])
            ->whereHas('member', fn($query) => $query->where('managed_by_user_id', $user->id))
            ->latest('effective_at')
            ->latest('id')
            ->get();

        return Inertia::render('Charges', [
            'summary' => [
                'total_charges' => $charges->count(),
                'total_charge_amount' => (int) $charges->sum('amount'),
                'pending_charges' => $charges->where('status', Charge::STATUS_PENDING)->count(),
                'settled_charges' => $charges->whereIn('status', [Charge::STATUS_POSTED, Charge::STATUS_WAIVED])->count(),
            ],
            'charges' => $charges
                ->map(fn(Charge $charge): array => [
                    'id' => $charge->id,
                    'member_name' => $charge->member?->full_name,
                    'charge_title' => $charge->category?->title,
                    'charge_code' => $charge->category?->code,
                    'amount' => $charge->amount,
                    'status' => $charge->status,
                    'effective_at' => $charge->effective_at?->format('d M Y, h:i A'),
                    'allocated_amount' => (int) $charge->allocations->whereNull('reversed_at')->sum('amount'),
                    'last_confirmed_at' => $charge->allocations
                        ->whereNull('reversed_at')
                        ->sortByDesc('confirmed_at')
                        ->first()?->confirmed_at?->format('d M Y, h:i A'),
                    'last_reversed_at' => $charge->allocations
                        ->whereNotNull('reversed_at')
                        ->sortByDesc('reversed_at')
                        ->first()?->reversed_at?->format('d M Y, h:i A'),
                ])
                ->values(),
            'allocationSummary' => [
                'active_charge_allocations' => ChargeAllocation::query()
                    ->whereNull('reversed_at')
                    ->whereHas('charge.member', fn($query) => $query->where('managed_by_user_id', $user->id))
                    ->count(),
            ],
        ]);
    }
}
