<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Models\ChargeAllocation;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Collection;
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

        $members = $user->managedMembers()
            ->select([
                'id',
                'full_name',
                'status',
                'units',
                'activated_at',
            ])
            ->latest('applied_at')
            ->latest('id')
            ->get();

        $openFundCycles = FundCycle::query()
            ->select([
                'id',
                'name',
                'status',
                'unit_amount',
                'lock_date',
                'slots',
            ])
            ->where('status', FundCycle::STATUS_OPEN)
            ->with([
                'allocations:id,fund_cycle_id,member_id,slot_key',
            ])
            ->latest('start_date')
            ->latest('id')
            ->get();

        $remainingPool = $this->remainingPoolForUser($user);

        $memberTabs = $members
            ->map(fn(Member $member): array => $this->transformMemberTab(
                member: $member,
                allocations: $allocations,
                openFundCycles: $openFundCycles,
            ))
            ->values();

        return Inertia::render('Allocations', [
            'summary' => [
                'total_allocations' => $allocations->count(),
                'total_allocated_amount' => (int) $allocations->sum('amount'),
                'member_count' => $members->count(),
                'cycle_count' => $allocations->pluck('fund_cycle_id')->unique()->count(),
                'available_to_allocate' => $remainingPool,
            ],
            'memberTabs' => $memberTabs,
        ]);
    }

    private function transformMemberTab(Member $member, Collection $allocations, Collection $openFundCycles): array
    {
        $memberAllocations = $allocations
            ->where('member_id', $member->id)
            ->values();

        $allocatedRows = $memberAllocations
            ->map(fn(FundCycleAllocation $allocation): array => [
                'row_key' => 'allocated-' . $allocation->id,
                'id' => $allocation->id,
                'status' => 'allocated',
                'cycle_id' => $allocation->fund_cycle_id,
                'cycle_name' => $allocation->fundCycle?->name,
                'cycle_status' => $allocation->fundCycle?->status,
                'slot_key' => $allocation->slot_key,
                'amount' => $allocation->amount,
                'allocated_at' => $allocation->allocated_at?->format('d M Y, h:i A'),
                'notes' => $allocation->notes,
                'can_allocate' => false,
            ])
            ->values();

        $canMemberAllocate = $member->status === MemberStatus::Approved && $member->activated_at !== null;

        $unallocatedRows = $openFundCycles
            ->flatMap(function (FundCycle $fundCycle) use ($member, $canMemberAllocate): Collection {
                $isCycleLocked = $fundCycle->lock_date !== null
                    && now()->startOfDay()->greaterThanOrEqualTo($fundCycle->lock_date);

                $cycleAllocations = $fundCycle->allocations;
                $memberTakenSlots = $cycleAllocations
                    ->where('member_id', $member->id)
                    ->pluck('slot_key')
                    ->filter(fn($slot) => is_string($slot) && trim($slot) !== '')
                    ->map(fn(string $slot) => trim($slot));

                $slots = collect($fundCycle->slots ?? [])
                    ->filter(fn($slot) => is_string($slot) && trim($slot) !== '')
                    ->map(fn(string $slot) => trim($slot));

                $availableSlots = $slots
                    ->reject(fn(string $slot) => $memberTakenSlots->contains($slot))
                    ->values();

                return $availableSlots
                    ->map(fn(string $slot): array => [
                        'row_key' => 'unallocated-' . $member->id . '-' . $fundCycle->id . '-' . str($slot)->slug('-'),
                        'id' => null,
                        'status' => 'unallocated',
                        'cycle_id' => $fundCycle->id,
                        'cycle_name' => $fundCycle->name,
                        'cycle_status' => $fundCycle->status,
                        'slot_key' => $slot,
                        'amount' => $fundCycle->allocationAmountFor($member->units),
                        'allocated_at' => null,
                        'notes' => null,
                        'can_allocate' => $canMemberAllocate && ! $isCycleLocked,
                    ])
                    ->values();
            })
            ->values();

        $rows = $allocatedRows
            ->concat($unallocatedRows)
            ->sortBy([
                ['status', 'desc'],
                ['cycle_name', 'asc'],
                ['slot_key', 'asc'],
            ])
            ->values();

        return [
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'status' => $member->status->value,
                'units' => $member->units,
                'activated_at' => $member->activated_at?->format('d M Y, h:i A'),
                'can_allocate' => $canMemberAllocate,
            ],
            'filters' => [
                'cycles' => $rows
                    ->pluck('cycle_name')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values(),
                'slots' => $rows
                    ->pluck('slot_key')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values(),
            ],
            'rows' => $rows,
        ];
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
