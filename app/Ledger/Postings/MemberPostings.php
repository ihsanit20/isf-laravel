<?php

namespace App\Ledger\Postings;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberPostings
{
    public function __construct(private readonly Ledger $ledger) {}

    public function availableBalance(int $userId): int
    {
        return $this->ledger->creditBalance(Account::MemberBalance, ['user_id' => $userId]);
    }

    /**
     * How a member balance (one user, or everyone when null) came to be, in
     * paisa. Outflows are positive amounts; the parts always add up:
     * deposits − fees − cycle_allocations + cycle_returns − payouts + other = available.
     *
     * @return array{deposits: int, fees: int, cycle_allocations: int, cycle_returns: int, payouts: int, other: int, available: int}
     */
    public function balanceBreakdown(?int $userId = null): array
    {
        $movements = $this->ledger
            ->movementsByKind(Account::MemberBalance, $userId === null ? [] : ['user_id' => $userId])
            ->map(fn (int $balance): int => -$balance);

        $known = ['deposit_verified', 'fee_settled', 'cycle_allocated', 'cycle_settled', 'member_payout'];

        return [
            'deposits' => $movements->get('deposit_verified', 0),
            'fees' => -$movements->get('fee_settled', 0),
            'cycle_allocations' => -$movements->get('cycle_allocated', 0),
            'cycle_returns' => $movements->get('cycle_settled', 0),
            'payouts' => -$movements->get('member_payout', 0),
            'other' => (int) $movements->except($known)->sum(),
            'available' => (int) $movements->sum(),
        ];
    }

    /**
     * Capital allocated to fund cycles, read from the allocation postings so it
     * stays the same after a cycle is settled.
     *
     * @param  array<string, int|null>  $dims
     * @return Collection<int|string, int> paisa keyed by the group column value
     */
    public function allocatedCapitalBy(string $groupColumn, array $dims = []): Collection
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.ledger_account_id', LedgerAccount::idFor(Account::CycleCapital))
            ->where('journal_entries.kind', 'like', 'cycle_allocated%')
            ->tap(function ($query) use ($dims): void {
                foreach ($dims as $column => $value) {
                    $query->where('journal_lines.'.$column, $value);
                }
            })
            ->selectRaw("journal_lines.{$groupColumn} as group_key")
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
            ->groupBy("journal_lines.{$groupColumn}")
            ->pluck('balance', 'group_key')
            ->map(fn ($balance): int => (int) $balance);
    }

    /**
     * Lock the user's pool and fail validation when spending exceeds it.
     */
    public function lockAndAssertAvailable(int $userId, int $amount, string $field, string $message): void
    {
        $this->ledger->lock('user:'.$userId);
        $this->ledger->assertAvailable(Account::MemberBalance, ['user_id' => $userId], $amount, $field, $message);
    }

    public function depositVerified(DepositSubmission $deposit, ?User $by = null): void
    {
        $amount = Money::toPaisa($deposit->amount);

        $this->ledger->entry('deposit_verified', "Deposit #{$deposit->id} verified")
            ->on($deposit->deposit_date)
            ->source($deposit)
            ->key('deposit:'.$deposit->id)
            ->by($by)
            ->debit(Account::Bank, $amount, ['fund_cycle_id' => null])
            ->credit(Account::MemberBalance, $amount, ['user_id' => $deposit->user_id])
            ->post();
    }

    public function chargeSettled(ChargeAllocation $allocation, ?User $by = null): void
    {
        $allocation->loadMissing('charge.member', 'charge.category');
        $member = $allocation->charge->member;
        $amount = Money::toPaisa($allocation->amount);
        $incomeAccount = $allocation->charge->category?->code === ChargeCategory::CODE_REGISTRATION_FEE
            ? Account::RegistrationFeeIncome
            : Account::OtherFeeIncome;
        $dims = ['user_id' => $member->managed_by_user_id, 'member_id' => $member->id];

        $this->ledger->entry('fee_settled', sprintf(
            '%s settled for %s',
            $allocation->charge->category?->title ?? 'Charge',
            $member->full_name,
        ))
            ->on($allocation->confirmed_at)
            ->source($allocation)
            ->key('charge-allocation:'.$allocation->id)
            ->by($by)
            ->debit(Account::MemberBalance, $amount, $dims)
            ->credit($incomeAccount, $amount, $dims)
            ->post();
    }

    public function chargeAllocationReversed(ChargeAllocation $allocation, ?User $by = null): void
    {
        $this->ledger->reverseSource($allocation, "Charge allocation #{$allocation->id} reversed", $by);
    }

    public function cycleAllocated(FundCycleAllocation $allocation, ?User $by = null): void
    {
        $allocation->loadMissing('member', 'fundCycle');
        $member = $allocation->member;
        $amount = Money::toPaisa($allocation->amount);
        $cycleId = (int) $allocation->fund_cycle_id;

        $this->ledger->entry('cycle_allocated', sprintf(
            '%s allocated to %s (%s)',
            $member->full_name,
            $allocation->fundCycle->name,
            $allocation->slot_key,
        ))
            ->on($allocation->allocated_at)
            ->source($allocation)
            ->key('cycle-allocation:'.$allocation->id)
            ->by($by)
            ->debit(Account::MemberBalance, $amount, ['user_id' => $member->managed_by_user_id, 'member_id' => $member->id])
            ->credit(Account::CycleCapital, $amount, [
                'user_id' => $member->managed_by_user_id,
                'member_id' => $member->id,
                'fund_cycle_id' => $cycleId,
            ])
            ->debit(Account::Bank, $amount, ['fund_cycle_id' => $cycleId], 'earmark to cycle')
            ->credit(Account::Bank, $amount, ['fund_cycle_id' => null], 'earmark to cycle')
            ->post();
    }

    /**
     * Allocate a member's slot after confirming the manager's pool covers it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function allocateToCycle(array $attributes, int $managerUserId): FundCycleAllocation
    {
        FundCycle::query()->findOrFail($attributes['fund_cycle_id'])->ensureNotSettled();

        return DB::transaction(function () use ($attributes, $managerUserId): FundCycleAllocation {
            $this->lockAndAssertAvailable(
                $managerUserId,
                Money::toPaisa($attributes['amount']),
                'slot_key',
                'Not enough available balance for this slot allocation.',
            );

            // The model posts the journal entry on create.
            return FundCycleAllocation::query()->create($attributes);
        });
    }

    public function payoutPaid(PayoutRequest $payout, ?User $by = null): void
    {
        $amount = Money::toPaisa($payout->amount);

        $this->ledger->entry('member_payout', "Payout #{$payout->id} paid")
            ->on($payout->processed_at ?? now())
            ->source($payout)
            ->key('payout:'.$payout->id)
            ->by($by)
            ->debit(Account::MemberBalance, $amount, ['user_id' => $payout->user_id])
            ->credit(Account::Bank, $amount, ['fund_cycle_id' => null])
            ->post();
    }
}
