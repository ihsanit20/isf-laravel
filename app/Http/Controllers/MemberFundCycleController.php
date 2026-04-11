<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Requests\Members\StoreMemberFundCycleAllocationRequest;
use App\Models\ChargeAllocation;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemberFundCycleController extends Controller
{
    public function index(Request $request, Member $member): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($member->managed_by_user_id === $user->id, 404);

        $remainingPool = $this->remainingPoolForUser($user);
        return Inertia::render('members/FundCycles', [
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'status' => $member->status->value,
                'units' => $member->units,
                'approved_at' => $member->approved_at?->format('d M Y, h:i A'),
                'activated_at' => $member->activated_at?->format('d M Y, h:i A'),
                'remaining_pool' => $remainingPool,
            ],
            'fundCycles' => FundCycle::query()
                ->withCount('allocations')
                ->withSum('allocations', 'amount')
                ->with([
                    'allocations' => fn($query) => $query
                        ->where('member_id', $member->id)
                        ->select(['id', 'fund_cycle_id', 'member_id', 'slot_key', 'amount']),
                ])
                ->where('status', FundCycle::STATUS_OPEN)
                ->latest('start_date')
                ->latest('id')
                ->get()
                ->map(fn(FundCycle $fundCycle): array => [
                    'id' => $fundCycle->id,
                    'name' => $fundCycle->name,
                    'status' => $fundCycle->status,
                    'status_label' => FundCycle::statusLabel($fundCycle->status),
                    'start_date' => $fundCycle->start_date?->format('d M Y'),
                    'lock_date' => $fundCycle->lock_date?->format('d M Y'),
                    'maturity_date' => $fundCycle->maturity_date?->format('d M Y'),
                    'settlement_date' => $fundCycle->settlement_date?->format('d M Y'),
                    'unit_amount' => $fundCycle->unit_amount,
                    'slots' => collect($fundCycle->slots ?? [])->values(),
                    'allocation_amount' => $fundCycle->allocationAmountFor($member->units),
                    'total_allocated_amount' => (int) ($fundCycle->allocations_sum_amount ?? 0),
                    'allocations_count' => $fundCycle->allocations_count,
                    'allocated_slots' => $fundCycle->allocations
                        ->pluck('slot_key')
                        ->filter()
                        ->values(),
                    'allocated_slot_amounts' => $fundCycle->allocations
                        ->filter(fn(FundCycleAllocation $allocation) => filled($allocation->slot_key))
                        ->mapWithKeys(fn(FundCycleAllocation $allocation): array => [
                            $allocation->slot_key => $allocation->amount,
                        ]),
                    'is_locked' => $fundCycle->lock_date !== null && now()->startOfDay()->greaterThanOrEqualTo($fundCycle->lock_date),
                    'can_allocate' => $member->status === MemberStatus::Approved
                        && $member->activated_at !== null
                        && ($fundCycle->lock_date === null || now()->startOfDay()->lt($fundCycle->lock_date))
                        && $remainingPool >= $fundCycle->allocationAmountFor($member->units),
                ])
                ->values(),
        ]);
    }

    public function store(
        StoreMemberFundCycleAllocationRequest $request,
        Member $member,
        FundCycle $fundCycle,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless($member->managed_by_user_id === $user->id, 404);

        DB::transaction(function () use ($request, $member, $fundCycle, $user): void {
            $fundCycle->allocations()->create([
                'member_id' => $member->id,
                'slot_key' => $request->string('slot_key')->trim()->toString(),
                'amount' => $fundCycle->allocationAmountFor($member->units),
                'allocated_at' => now(),
                'notes' => $request->validated('notes'),
                'created_by_user_id' => $user->id,
            ]);
        });

        return to_route('members.fund-cycles.index', $member);
    }

    private function remainingPoolForUser(User $user): int
    {
        $verifiedDepositAmount = (int) DepositSubmission::query()
            ->where('user_id', $user->id)
            ->where('status', DepositSubmissionStatus::Verified)
            ->sum('amount');

        $chargeAllocatedAmount = (int) ChargeAllocation::query()
            ->whereNull('reversed_at')
            ->whereHas('charge.member', fn($query) => $query->where('managed_by_user_id', $user->id))
            ->sum('amount');

        $cycleAllocatedAmount = (int) FundCycleAllocation::query()
            ->whereHas('member', fn($query) => $query->where('managed_by_user_id', $user->id))
            ->sum('amount');

        return max(0, $verifiedDepositAmount - $chargeAllocatedAmount - $cycleAllocatedAmount);
    }
}
