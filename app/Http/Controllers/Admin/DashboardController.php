<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Controllers\Concerns\BuildsActivityFeed;
use App\Http\Controllers\Controller;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\Charge;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BuildsActivityFeed;

    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'overview' => $this->buildOverview(),
        ]);
    }

    private function buildOverview(): array
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
            'recent_activity' => $this->buildRecentActivity(),
        ];
    }

    private function buildRecentActivity(): array
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
}
