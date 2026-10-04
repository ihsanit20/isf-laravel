<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
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
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'personal' => $this->buildPersonalDashboard($user),
            'adminOverview' => $user->hasAdminAccess()
                ? $this->buildAdminDashboard()
                : null,
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

        $nextOpenCycle = FundCycle::query()
            ->where('status', FundCycle::STATUS_OPEN)
            ->orderBy('lock_date')
            ->orderBy('start_date')
            ->first();

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
            'next_cycle' => $nextOpenCycle ? [
                'name' => $nextOpenCycle->name,
                'lock_date' => $nextOpenCycle->lock_date?->format('d M Y'),
                'unit_amount' => $nextOpenCycle->unit_amount,
                'status_label' => FundCycle::statusLabel($nextOpenCycle->status),
            ] : null,
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
            ->latest('applied_at')
            ->latest('id')
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

    private function buildAdminDashboard(): array
    {
        $pool = app(MemberPostings::class)->balanceBreakdown();

        return [
            'pool_summary' => [
                'total_verified_deposits' => Money::toTaka($pool['deposits']),
                'total_charge_allocations' => Money::toTaka($pool['fees']),
                'total_cycle_allocations' => Money::toTaka($pool['cycle_allocations']),
                'total_cycle_returns' => Money::toTaka($pool['cycle_returns']),
                'total_payouts' => Money::toTaka($pool['payouts']),
                'remaining_pool' => Money::toTaka($pool['available']),
                'platform_fund' => Money::toTaka(app(Ledger::class)->platformFund()),
            ],
            'queues' => [
                'pending_deposits' => DepositSubmission::query()
                    ->where('status', DepositSubmissionStatus::Pending)
                    ->count(),
                'pending_members' => Member::query()
                    ->where('status', MemberStatus::Pending)
                    ->count(),
                'approved_not_activated_members' => Member::query()
                    ->where('status', MemberStatus::Approved)
                    ->whereNull('activated_at')
                    ->count(),
                'pending_charges' => Charge::query()
                    ->where('status', Charge::STATUS_PENDING)
                    ->count(),
            ],
            'cycle_statuses' => collect(FundCycle::statuses())
                ->map(fn (string $status): array => [
                    'status' => $status,
                    'label' => FundCycle::statusLabel($status),
                    'count' => FundCycle::query()->where('status', $status)->count(),
                ])
                ->values(),
            'recent_activity' => $this->buildAdminRecentActivity(),
        ];
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

    private function buildAdminRecentActivity(): array
    {
        $verifiedDeposits = DepositSubmission::query()
            ->with('user:id,name')
            ->where('status', DepositSubmissionStatus::Verified)
            ->whereNotNull('verified_at')
            ->latest('verified_at')
            ->limit(3)
            ->get()
            ->map(fn (DepositSubmission $deposit): array => $this->makeActivityItem(
                id: 'admin-deposit-'.$deposit->id,
                title: 'Deposit verified',
                description: sprintf(
                    '%s BDT verified for %s.',
                    number_format($deposit->amount),
                    $deposit->user?->name ?? 'a user',
                ),
                timestamp: $deposit->verified_at,
                tone: 'success',
            ));

        $approvedMembers = Member::query()
            ->with('manager:id,name')
            ->where('status', MemberStatus::Approved)
            ->whereNotNull('approved_at')
            ->latest('approved_at')
            ->limit(3)
            ->get()
            ->map(fn (Member $member): array => $this->makeActivityItem(
                id: 'admin-member-'.$member->id,
                title: 'Member approved',
                description: sprintf(
                    '%s approved under %s.',
                    $member->full_name,
                    $member->manager?->name ?? 'a user',
                ),
                timestamp: $member->approved_at,
                tone: 'success',
            ));

        $cycleAllocations = FundCycleAllocation::query()
            ->with(['member:id,full_name', 'fundCycle:id,name'])
            ->latest('allocated_at')
            ->limit(3)
            ->get()
            ->map(fn (FundCycleAllocation $allocation): array => $this->makeActivityItem(
                id: 'admin-allocation-'.$allocation->id,
                title: 'Cycle allocation posted',
                description: sprintf(
                    '%s allocated to %s.',
                    $allocation->member?->full_name ?? 'A member',
                    $allocation->fundCycle?->name ?? 'a cycle',
                ),
                timestamp: $allocation->allocated_at,
                tone: 'warning',
            ));

        return $this->sortActivity($verifiedDeposits, $approvedMembers, $cycleAllocations);
    }

    private function sortActivity(Collection ...$groups): array
    {
        return collect($groups)
            ->flatten(1)
            ->sortByDesc('sort_value')
            ->take(6)
            ->map(function (array $item): array {
                unset($item['sort_value']);

                return $item;
            })
            ->values()
            ->all();
    }

    private function makeActivityItem(
        string $id,
        string $title,
        string $description,
        mixed $timestamp,
        string $tone,
    ): array {
        $sortValue = $timestamp?->getTimestamp() ?? 0;

        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'timestamp' => $timestamp?->format('d M Y, h:i A'),
            'tone' => $tone,
            'sort_value' => $sortValue,
        ];
    }
}
