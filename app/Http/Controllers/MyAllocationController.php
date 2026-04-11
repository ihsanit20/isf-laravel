<?php

namespace App\Http\Controllers;

use App\Models\FundCycleAllocation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyAllocationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $allocations = FundCycleAllocation::query()
            ->with([
                'member:id,full_name,managed_by_user_id',
                'fundCycle:id,name,status',
            ])
            ->whereHas('member', fn($query) => $query->where('managed_by_user_id', $user->id))
            ->latest('allocated_at')
            ->latest('id')
            ->get();

        return Inertia::render('Allocations', [
            'summary' => [
                'total_allocations' => $allocations->count(),
                'total_allocated_amount' => (int) $allocations->sum('amount'),
                'member_count' => $allocations->pluck('member_id')->unique()->count(),
                'cycle_count' => $allocations->pluck('fund_cycle_id')->unique()->count(),
            ],
            'allocations' => $allocations
                ->map(fn(FundCycleAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'member_name' => $allocation->member?->full_name,
                    'cycle_name' => $allocation->fundCycle?->name,
                    'cycle_status' => $allocation->fundCycle?->status,
                    'slot_key' => $allocation->slot_key,
                    'amount' => $allocation->amount,
                    'allocated_at' => $allocation->allocated_at?->format('d M Y, h:i A'),
                    'notes' => $allocation->notes,
                ])
                ->values(),
        ]);
    }
}
