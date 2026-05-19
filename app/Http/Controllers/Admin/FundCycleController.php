<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFundCycleAllocationRequest;
use App\Http\Requests\Admin\StoreFundCycleRequest;
use App\Http\Requests\Admin\UpdateFundCycleRequest;
use App\Models\ChargeAllocation;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FundCycleController extends Controller
{
    public function index(): Response
    {
        $totalVerifiedDeposits = (int) DepositSubmission::query()
            ->where('status', DepositSubmissionStatus::Verified)
            ->sum('amount');
        $totalChargeAllocations = (int) ChargeAllocation::query()
            ->whereNull('reversed_at')
            ->sum('amount');
        $totalCycleAllocations = (int) FundCycleAllocation::query()->sum('amount');

        return Inertia::render('admin/FundCycles', [
            'fundCycles' => FundCycle::query()
                ->withCount('allocations')
                ->withSum('allocations', 'amount')
                ->with(['creator:id,name'])
                ->latest('start_date')
                ->latest('id')
                ->get()
                ->map(fn(FundCycle $fundCycle): array => [
                    'id' => $fundCycle->id,
                    'name' => $fundCycle->name,
                    'status' => $fundCycle->status,
                    'status_label' => FundCycle::statusLabel($fundCycle->status),
                    'unit_amount' => $fundCycle->unit_amount,
                    'start_date' => $fundCycle->start_date?->format('Y-m-d'),
                    'lock_date' => $fundCycle->lock_date?->format('Y-m-d'),
                    'maturity_date' => $fundCycle->maturity_date?->format('Y-m-d'),
                    'settlement_date' => $fundCycle->settlement_date?->format('Y-m-d'),
                    'slots' => collect($fundCycle->slots ?? [])->values(),
                    'notes' => $fundCycle->notes,
                    'has_allocations' => $fundCycle->allocations_count > 0,
                    'allocations_count' => $fundCycle->allocations_count,
                    'created_by' => $fundCycle->creator?->name,
                    'created_at' => $fundCycle->created_at?->format('d M Y, h:i A'),
                    'allocated_amount' => (int) ($fundCycle->allocations_sum_amount ?? 0),
                ])
                ->values(),
            'statuses' => FundCycle::statuses(),
            'eligibleMembers' => Member::query()
                ->where('status', MemberStatus::Approved)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'units'])
                ->map(fn(Member $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'units' => $member->units,
                ])
                ->values(),
            'poolSummary' => [
                'total_verified_deposits' => $totalVerifiedDeposits,
                'total_charge_allocations' => $totalChargeAllocations,
                'total_cycle_allocations' => $totalCycleAllocations,
                'remaining_pool' => max(0, $totalVerifiedDeposits - $totalChargeAllocations - $totalCycleAllocations),
            ],
        ]);
    }

    public function show(FundCycle $fundCycle): Response
    {
        $fundCycle->load([
            'creator:id,name',
            'allocations.member:id,full_name,managed_by_user_id',
            'allocations.member.manager:id,name,email',
        ]);

        // Get all users who have approved members
        $usersWithMembers = User::query()
            ->whereHas('managedMembers', fn($query) => $query->where('status', MemberStatus::Approved))
            ->with(['managedMembers' => fn($query) => $query->where('status', MemberStatus::Approved)->orderBy('full_name')])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        $slots = collect($fundCycle->slots ?? []);

        // Calculate total units from all approved members
        $totalMembers = $usersWithMembers->sum(fn($user) => $user->managedMembers->count());
        $totalUnits = $usersWithMembers->sum(fn($user) => $user->managedMembers->sum('units'));

        // Calculate allocation statistics
        $totalUsers = $usersWithMembers->count();
        $totalSlots = $slots->count();
        $allocatedAmount = (int) $fundCycle->allocations->sum('amount');
        $allocationsCount = $fundCycle->allocations->count();
        $expectedAllocations = $totalUnits * $totalSlots;
        $expectedAmount = $expectedAllocations * $fundCycle->unit_amount;
        $remainingAllocations = $expectedAllocations - $allocationsCount;
        $remainingAmount = $expectedAmount - $allocatedAmount;

        // Calculate missing allocations by user
        $existingAllocations = $fundCycle->allocations
            ->groupBy(fn($allocation) => ($allocation->member?->managed_by_user_id ?? 0) . '-' . $allocation->slot_key);

        $missingAllocations = [];
        foreach ($usersWithMembers as $user) {
            foreach ($slots as $slot) {
                $key = $user->id . '-' . $slot;
                if (!$existingAllocations->has($key)) {
                    $memberNames = $user->managedMembers->pluck('full_name')->join(', ');
                    $missingAllocations[] = [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_phone' => $user->phone,
                        'member_names' => $memberNames,
                        'slot_key' => $slot,
                    ];
                }
            }
        }

        return Inertia::render('admin/FundCycleDetails', [
            'fundCycle' => [
                'id' => $fundCycle->id,
                'name' => $fundCycle->name,
                'status' => $fundCycle->status,
                'status_label' => FundCycle::statusLabel($fundCycle->status),
                'unit_amount' => $fundCycle->unit_amount,
                'start_date' => $fundCycle->start_date?->format('Y-m-d'),
                'lock_date' => $fundCycle->lock_date?->format('Y-m-d'),
                'maturity_date' => $fundCycle->maturity_date?->format('Y-m-d'),
                'settlement_date' => $fundCycle->settlement_date?->format('Y-m-d'),
                'slots' => $slots->values(),
                'notes' => $fundCycle->notes,
                'has_allocations' => $allocationsCount > 0,
                'created_by' => $fundCycle->creator?->name,
                'created_at' => $fundCycle->created_at?->format('d M Y, h:i A'),
                'total_users' => $totalUsers,
                'total_members' => $totalMembers,
                'total_units' => $totalUnits,
                'total_slots' => $totalSlots,
                'expected_allocations' => $expectedAllocations,
                'expected_amount' => $expectedAmount,
                'allocated_amount' => $allocatedAmount,
                'allocations_count' => $allocationsCount,
                'remaining_allocations' => $remainingAllocations,
                'remaining_amount' => $remainingAmount,
                'allocations' => $fundCycle->allocations
                    ->sortByDesc('allocated_at')
                    ->values()
                    ->map(fn(FundCycleAllocation $allocation): array => [
                        'id' => $allocation->id,
                        'member_id' => $allocation->member_id,
                        'member_name' => $allocation->member?->full_name,
                        'user_id' => $allocation->member?->managed_by_user_id,
                        'user_name' => $allocation->member?->manager?->name,
                        'slot_key' => $allocation->slot_key,
                        'amount' => $allocation->amount,
                        'allocated_at' => $allocation->allocated_at?->format('d M Y, h:i A'),
                        'notes' => $allocation->notes,
                    ]),
            ],
            'users' => $usersWithMembers->map(fn(User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'member_names' => $user->managedMembers->pluck('full_name')->join(', '),
            ])->values(),
            'missingAllocations' => $missingAllocations,
            'eligibleMembers' => Member::query()
                ->where('status', MemberStatus::Approved)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'units'])
                ->map(fn(Member $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'units' => $member->units,
                ])
                ->values(),
            'statuses' => FundCycle::statuses(),
        ]);
    }

    public function store(StoreFundCycleRequest $request): RedirectResponse
    {
        FundCycle::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.fund-cycles.index');
    }

    public function update(UpdateFundCycleRequest $request, FundCycle $fundCycle): RedirectResponse
    {
        $fundCycle->update($request->validated());

        return to_route('admin.fund-cycles.index');
    }

    public function storeAllocation(StoreFundCycleAllocationRequest $request, FundCycle $fundCycle): RedirectResponse
    {
        $member = Member::query()->findOrFail((int) $request->integer('member_id'));

        DB::transaction(function () use ($request, $fundCycle, $member): void {
            $fundCycle->allocations()->create([
                'member_id' => $member->id,
                'slot_key' => $request->string('slot_key')->trim()->toString(),
                'amount' => $fundCycle->allocationAmountFor($member->units),
                'notes' => $request->validated('notes'),
                'allocated_at' => now(),
                'created_by_user_id' => $request->user()?->id,
            ]);
        });

        return to_route('admin.fund-cycles.index');
    }
}
