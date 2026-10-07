<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFundCycleAllocationRequest;
use App\Http\Requests\Admin\StoreFundCycleRequest;
use App\Http\Requests\Admin\UpdateFundCycleRequest;
use App\Ledger\Money;
use App\Ledger\Postings\CyclePostings;
use App\Ledger\Postings\MemberPostings;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleEvent;
use App\Models\FundCycleTransaction;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FundCycleController extends Controller
{
    public function index(): Response
    {
        $allocatedByCycle = app(MemberPostings::class)->allocatedCapitalBy('fund_cycle_id');

        return Inertia::render('admin/FundCycles', [
            'fundCycles' => FundCycle::query()
                ->withCount('allocations')
                ->with(['creator:id,name'])
                ->latest('start_date')
                ->latest('id')
                ->get()
                ->map(fn (FundCycle $fundCycle): array => [
                    'id' => $fundCycle->id,
                    'name' => $fundCycle->name,
                    'status' => $fundCycle->status,
                    'status_label' => FundCycle::statusLabel($fundCycle->status),
                    'is_settled' => $fundCycle->isSettled(),
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
                    'allocated_amount' => Money::toTaka($allocatedByCycle->get($fundCycle->id, 0)),
                ])
                ->values(),
            'statuses' => FundCycle::editableStatuses(),
            'eligibleMembers' => Member::query()
                ->where('status', MemberStatus::Approved)
                ->orderBy('managed_by_user_id')
                ->orderBy('id')
                ->get(['id', 'full_name', 'units'])
                ->map(fn (Member $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'units' => $member->units,
                ])
                ->values(),
        ]);
    }

    public function show(FundCycle $fundCycle): Response
    {
        $fundCycle->load(['creator:id,name'])
            ->loadCount('allocations');

        $usersWithMembers = $this->usersWithApprovedMembers($fundCycle);
        $slots = collect($fundCycle->slots ?? []);

        $totalMembers = $usersWithMembers->sum(fn ($user) => $user->managedMembers->count());
        $totalUnits = $usersWithMembers->sum(fn ($user) => $user->managedMembers->sum('units'));
        $totalUsers = $usersWithMembers->count();
        $totalSlots = $slots->count();
        $allocatedAmount = $this->allocatedAmount($fundCycle);
        $allocationsCount = (int) ($fundCycle->allocations_count ?? 0);
        $expectedAllocations = $totalUnits * $totalSlots;
        $expectedAmount = $expectedAllocations * $fundCycle->unit_amount;
        $remainingAllocations = $expectedAllocations - $allocationsCount;
        $remainingAmount = $expectedAmount - $allocatedAmount;

        return Inertia::render('admin/FundCycleDetails', [
            'fundCycle' => [
                'id' => $fundCycle->id,
                'name' => $fundCycle->name,
                'status' => $fundCycle->status,
                'status_label' => FundCycle::statusLabel($fundCycle->status),
                'is_settled' => $fundCycle->isSettled(),
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
                'settled_at' => $fundCycle->settled_at?->format('d M Y, h:i A'),
            ],
            'statuses' => FundCycle::editableStatuses(),
            'ledger' => $this->cycleLedger($fundCycle),
            'transactions' => $fundCycle->transactions()
                ->with('createdBy:id,name')
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (FundCycleTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'direction' => $transaction->direction,
                    'category' => $transaction->category,
                    'category_label' => FundCycleTransaction::categoryLabel($transaction->category),
                    'amount' => (float) $transaction->amount,
                    'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
                    'description' => $transaction->description,
                    'created_by_name' => $transaction->createdBy?->name,
                ])
                ->values(),
            'transactionCategories' => collect(FundCycleTransaction::CATEGORIES)
                ->map(fn (string $category): array => ['value' => $category, 'label' => FundCycleTransaction::categoryLabel($category)])
                ->values(),
        ]);
    }

    public function allocations(FundCycle $fundCycle): Response
    {
        $fundCycle->load([
            'creator:id,name',
            'allocations.member:id,full_name,managed_by_user_id',
            'allocations.member.manager:id,name,email',
        ]);

        $usersWithMembers = $this->usersWithApprovedMembers($fundCycle);

        $slots = collect($fundCycle->slots ?? [])
            ->sortByDesc(fn (string $slot): int => $this->slotSortValue($slot))
            ->values();

        $totalMembers = $usersWithMembers->sum(fn ($user) => $user->managedMembers->count());
        $totalUnits = $usersWithMembers->sum(fn ($user) => $user->managedMembers->sum('units'));

        $totalUsers = $usersWithMembers->count();
        $totalSlots = $slots->count();
        $allocatedAmount = $this->allocatedAmount($fundCycle);
        $allocationsCount = $fundCycle->allocations->count();
        $expectedAllocations = $totalUnits * $totalSlots;
        $expectedAmount = $expectedAllocations * $fundCycle->unit_amount;
        $remainingAllocations = $expectedAllocations - $allocationsCount;
        $remainingAmount = $expectedAmount - $allocatedAmount;

        $existingAllocations = $fundCycle->allocations
            ->keyBy(fn ($allocation) => $allocation->member_id.'-'.$allocation->slot_key);

        $missingAllocations = [];
        foreach ($slots as $slot) {
            foreach ($usersWithMembers as $user) {
                $missingMembers = $user->managedMembers
                    ->reject(fn (Member $member) => $existingAllocations->has($member->id.'-'.$slot));

                if ($missingMembers->isNotEmpty()) {
                    $missingAllocations[] = [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_phone' => $user->phone,
                        'member_names' => $missingMembers->pluck('full_name')->join(', '),
                        'slot_key' => $slot,
                    ];
                }
            }
        }

        return Inertia::render('admin/FundCycleAllocations', [
            'fundCycle' => [
                'id' => $fundCycle->id,
                'name' => $fundCycle->name,
                'status' => $fundCycle->status,
                'status_label' => FundCycle::statusLabel($fundCycle->status),
                'is_settled' => $fundCycle->isSettled(),
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
                    ->sortBy([
                        fn ($a, $b) => $this->slotSortValue($b->slot_key) <=> $this->slotSortValue($a->slot_key),
                        fn ($a, $b) => $b->allocated_at <=> $a->allocated_at,
                    ])
                    ->values()
                    ->map(fn (FundCycleAllocation $allocation): array => [
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
            'users' => $usersWithMembers->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'member_names' => $user->managedMembers->pluck('full_name')->join(', '),
            ])->values(),
            'missingAllocations' => $missingAllocations,
            'eligibleMembers' => Member::query()
                ->where('status', MemberStatus::Approved)
                ->orderBy('managed_by_user_id')
                ->orderBy('id')
                ->get(['id', 'full_name', 'units'])
                ->map(fn (Member $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'units' => $member->units,
                ])
                ->values(),
            'statuses' => FundCycle::editableStatuses(),
        ]);
    }

    public function events(FundCycle $fundCycle): Response
    {
        return Inertia::render('admin/FundCycleEvents', [
            'fundCycle' => [
                'id' => $fundCycle->id,
                'name' => $fundCycle->name,
                'status' => $fundCycle->status,
                'status_label' => FundCycle::statusLabel($fundCycle->status),
                'is_settled' => $fundCycle->isSettled(),
                'start_date' => $fundCycle->start_date?->format('Y-m-d'),
                'lock_date' => $fundCycle->lock_date?->format('Y-m-d'),
                'maturity_date' => $fundCycle->maturity_date?->format('Y-m-d'),
                'settlement_date' => $fundCycle->settlement_date?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cycleLedger(FundCycle $fundCycle): array
    {
        $summary = app(CyclePostings::class)->summary($fundCycle);

        return [
            'capital' => Money::toTaka($summary['capital']),
            'cash' => Money::toTaka($summary['cash']),
            'deployed' => Money::toTaka($summary['deployed']),
            'investments_result' => Money::toTaka($summary['investments_result']),
            'cycle_income' => Money::toTaka($summary['cycle_income']),
            'cycle_expense' => Money::toTaka($summary['cycle_expense']),
            'result' => Money::toTaka($summary['result']),
            'is_settled' => $summary['is_settled'],
            'blockers' => $summary['blockers'],
            'investments' => collect($summary['investments'])
                ->map(fn (array $investment): array => [
                    ...$investment,
                    'result' => Money::toTaka($investment['result']),
                    'url' => $investment['type'] === 'event'
                        ? optional(FundCycleEvent::query()->where('cycle_investment_id', $investment['id'])->first(), fn ($event) => '/admin/events/'.$event->id)
                        : '/admin/businesses/'.$investment['id'],
                ])
                ->values(),
            'members' => collect($summary['members'])
                ->map(fn (array $member): array => [
                    ...$member,
                    'capital' => Money::toTaka($member['capital']),
                    'share' => Money::toTaka($member['share']),
                    'payout' => Money::toTaka($member['payout']),
                ])
                ->values(),
        ];
    }

    private function slotSortValue(?string $slot): int
    {
        if ($slot === null || trim($slot) === '') {
            return PHP_INT_MIN;
        }

        try {
            return Carbon::createFromFormat('!F Y', trim($slot))->timestamp;
        } catch (\Throwable) {
            return PHP_INT_MIN;
        }
    }

    private function allocatedAmount(FundCycle $fundCycle): float
    {
        return Money::toTaka((int) app(MemberPostings::class)
            ->allocatedCapitalBy('fund_cycle_id', ['fund_cycle_id' => $fundCycle->id])
            ->sum());
    }

    private function usersWithApprovedMembers(FundCycle $fundCycle)
    {
        $cutoff = $this->memberCutoff($fundCycle);

        $eligibleMembers = fn ($query) => $query
            ->where('status', MemberStatus::Approved)
            ->when($cutoff, fn ($query) => $query->where('created_at', '<', $cutoff));

        return User::query()
            ->whereHas('managedMembers', $eligibleMembers)
            ->with([
                'managedMembers' => fn ($query) => $eligibleMembers($query)->orderBy('id'),
            ])
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'phone']);
    }

    /**
     * Members created after allocations close (lock date or settlement) can never allocate to this cycle.
     */
    private function memberCutoff(FundCycle $fundCycle): ?CarbonInterface
    {
        return collect([$fundCycle->lock_date, $fundCycle->settled_at])
            ->filter()
            ->min();
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
        $fundCycle->ensureNotSettled();

        $fundCycle->update($request->validated());

        return to_route('admin.fund-cycles.index');
    }

    public function storeAllocation(StoreFundCycleAllocationRequest $request, FundCycle $fundCycle): RedirectResponse
    {
        $member = Member::query()->findOrFail((int) $request->integer('member_id'));

        app(MemberPostings::class)->allocateToCycle([
            'fund_cycle_id' => $fundCycle->id,
            'member_id' => $member->id,
            'slot_key' => $request->string('slot_key')->trim()->toString(),
            'amount' => $fundCycle->allocationAmountFor($member->units),
            'notes' => $request->validated('notes'),
            'allocated_at' => now(),
            'created_by_user_id' => $request->user()?->id,
        ], (int) $member->managed_by_user_id);

        return to_route('admin.fund-cycles.index');
    }
}
