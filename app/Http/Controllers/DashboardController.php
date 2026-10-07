<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Controllers\Concerns\BuildsActivityFeed;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\Charge;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BuildsActivityFeed;

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'personal' => $this->buildPersonalDashboard($user),
        ]);
    }

    private function buildPersonalDashboard(User $user): array
    {
        $managedMembersQuery = Member::query()->where('managed_by_user_id', $user->id);

        $totalMembers = (clone $managedMembersQuery)->count();
        $approvedMembers = (clone $managedMembersQuery)
            ->where('status', MemberStatus::Approved)
            ->count();
        $activeMembers = (clone $managedMembersQuery)
            ->where('status', MemberStatus::Approved)
            ->whereNotNull('activated_at')
            ->count();
        $totalUnits = (int) (clone $managedMembersQuery)->sum('units');

        $balance = app(MemberPostings::class)->balanceBreakdown($user->id);
        $pendingDepositCount = DepositSubmission::query()
            ->where('user_id', $user->id)
            ->where('status', DepositSubmissionStatus::Pending)
            ->count();
        $myChargesQuery = Charge::query()
            ->whereHas('member', fn ($query) => $query->where('managed_by_user_id', $user->id));

        $myChargeCount = (clone $myChargesQuery)->count();
        $pendingChargeCount = (clone $myChargesQuery)
            ->whereIn('status', [Charge::STATUS_PENDING, Charge::STATUS_CANCELLED])
            ->count();

        $myCycleAllocationCount = FundCycleAllocation::query()
            ->whereHas('member', fn ($query) => $query->where('managed_by_user_id', $user->id))
            ->count();

        $openCycleCount = FundCycle::query()
            ->where('status', FundCycle::STATUS_OPEN)
            ->count();

        $runningCycles = FundCycle::query()
            ->where('status', FundCycle::STATUS_OPEN)
            ->where(fn ($query) => $query
                ->whereNull('lock_date')
                ->orWhereDate('lock_date', '>', now()->toDateString()))
            ->orderBy('lock_date')
            ->orderBy('start_date')
            ->get();

        return [
            'summary' => [
                'total_members' => $totalMembers,
                'approved_members' => $approvedMembers,
                'active_members' => $activeMembers,
                'total_units' => $totalUnits,
                'verified_deposits' => Money::toTaka($balance['deposits']),
                'available_balance' => Money::toTaka($balance['available']),
            ],
            'actions' => [
                'pending_deposit_count' => $pendingDepositCount,
                'my_charge_count' => $myChargeCount,
                'pending_charge_count' => $pendingChargeCount,
                'my_cycle_allocation_count' => $myCycleAllocationCount,
                'open_cycle_count' => $openCycleCount,
            ],
            'running_cycles' => $runningCycles
                ->map(fn (FundCycle $cycle): array => [
                    'id' => $cycle->id,
                    'name' => $cycle->name,
                    'lock_date' => $cycle->lock_date?->format('d M Y'),
                    'unit_amount' => $cycle->unit_amount,
                    'status_label' => FundCycle::statusLabel($cycle->status),
                ])
                ->values()
                ->all(),
            'balance' => [
                'deposits' => Money::toTaka($balance['deposits']),
                'fees' => Money::toTaka($balance['fees']),
                'cycle_allocations' => Money::toTaka($balance['cycle_allocations']),
                'cycle_returns' => Money::toTaka($balance['cycle_returns']),
                'payouts' => Money::toTaka($balance['payouts']),
                'available' => Money::toTaka($balance['available']),
            ],
            'members' => $this->buildPersonalMembers($user),
            'recent_activity' => $this->buildPersonalRecentActivity($user),
        ];
    }

    private function buildPersonalMembers(User $user): array
    {
        $capitalByMember = app(Ledger::class)
            ->balancesBy('member_id', Account::CycleCapital, ['user_id' => $user->id])
            ->map(fn (int $balance): int => -$balance);

        return Member::query()
            ->where('managed_by_user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'full_name', 'status', 'units', 'activated_at'])
            ->map(fn (Member $member): array => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'status' => $member->status->value,
                'is_active' => $member->isActive(),
                'units' => $member->units,
                'capital_in_cycles' => Money::toTaka($capitalByMember->get($member->id, 0)),
            ])
            ->values()
            ->all();
    }

    private function buildPersonalRecentActivity(User $user): array
    {
        $deposits = DepositSubmission::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn (DepositSubmission $deposit): array => $this->makeActivityItem(
                id: 'deposit-'.$deposit->id,
                title: 'Deposit submitted',
                description: sprintf('%s BDT marked as %s.', number_format($deposit->amount), $deposit->status->value),
                timestamp: $deposit->created_at,
                tone: $deposit->status === DepositSubmissionStatus::Rejected ? 'danger' : ($deposit->status === DepositSubmissionStatus::Verified ? 'success' : 'warning'),
            ));

        $members = Member::query()
            ->where('managed_by_user_id', $user->id)
            ->latest('applied_at')
            ->limit(3)
            ->get()
            ->map(fn (Member $member): array => $this->makeActivityItem(
                id: 'member-'.$member->id,
                title: 'Membership updated',
                description: sprintf('%s is currently %s.', $member->full_name, $member->status->value),
                timestamp: $member->applied_at ?? $member->created_at,
                tone: $member->status === MemberStatus::Rejected ? 'danger' : ($member->isActive() ? 'success' : 'warning'),
            ));

        $allocations = FundCycleAllocation::query()
            ->with(['member:id,full_name', 'fundCycle:id,name'])
            ->whereHas('member', fn ($query) => $query->where('managed_by_user_id', $user->id))
            ->latest('allocated_at')
            ->limit(3)
            ->get()
            ->map(fn (FundCycleAllocation $allocation): array => $this->makeActivityItem(
                id: 'allocation-'.$allocation->id,
                title: 'Fund cycle allocated',
                description: sprintf(
                    '%s joined %s%s.',
                    $allocation->member?->full_name ?? 'A member',
                    $allocation->fundCycle?->name ?? 'a fund cycle',
                    $allocation->slot_key ? ' in '.$allocation->slot_key : '',
                ),
                timestamp: $allocation->allocated_at,
                tone: 'success',
            ));

        return $this->sortActivity($deposits, $members, $allocations);
    }
}
