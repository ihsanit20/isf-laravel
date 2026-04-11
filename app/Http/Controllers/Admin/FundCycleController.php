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
                ->with(['creator:id,name', 'allocations.member:id,full_name'])
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
                    'created_by' => $fundCycle->creator?->name,
                    'created_at' => $fundCycle->created_at?->format('d M Y, h:i A'),
                    'allocated_amount' => (int) $fundCycle->allocations->sum('amount'),
                    'allocations' => $fundCycle->allocations
                        ->sortByDesc('allocated_at')
                        ->values()
                        ->map(fn(FundCycleAllocation $allocation): array => [
                            'id' => $allocation->id,
                            'member_name' => $allocation->member?->full_name,
                            'slot_key' => $allocation->slot_key,
                            'amount' => $allocation->amount,
                            'allocated_at' => $allocation->allocated_at?->format('d M Y, h:i A'),
                            'notes' => $allocation->notes,
                        ]),
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
