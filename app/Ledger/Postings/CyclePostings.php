<?php

namespace App\Ledger\Postings;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\CycleInvestment;
use App\Models\FundCycle;
use App\Models\FundCycleTransaction;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The fund cycle as a mudaraba business: cycle-level income/expense and the
 * final settlement that returns capital ± result to member balances.
 * The platform takes no share of the cycle result.
 */
class CyclePostings
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly InvestmentPostings $investments,
    ) {}

    public function transaction(FundCycleTransaction $transaction, ?User $by = null): void
    {
        $cycleId = (int) $transaction->fund_cycle_id;
        $amount = Money::toPaisa($transaction->amount);
        $isIncome = $transaction->direction === 'income';

        DB::transaction(function () use ($transaction, $cycleId, $amount, $isIncome, $by): void {
            $this->ensureNotSettled($transaction->fundCycle);

            if (! $isIncome) {
                $this->ledger->lock('cycle:'.$cycleId);
                $this->ledger->assertAvailable(Account::Bank, ['fund_cycle_id' => $cycleId], $amount, 'amount', 'Not enough cycle money in the bank.');
            }

            $entry = $this->ledger->entry(
                $isIncome ? 'cycle_income' : 'cycle_expense',
                sprintf('Cycle %s: %s', $transaction->direction, FundCycleTransaction::categoryLabel($transaction->category)),
            )
                ->on($transaction->transaction_date)
                ->source($transaction)
                ->key('cycle-transaction:'.$transaction->id)
                ->by($by);

            $isIncome
                ? $entry->debit(Account::Bank, $amount, ['fund_cycle_id' => $cycleId])->credit(Account::CycleIncome, $amount, ['fund_cycle_id' => $cycleId])
                : $entry->debit(Account::CycleExpense, $amount, ['fund_cycle_id' => $cycleId])->credit(Account::Bank, $amount, ['fund_cycle_id' => $cycleId]);

            $entry->post();
        });
    }

    public function removed(FundCycleTransaction $transaction, ?User $by = null): void
    {
        $this->ensureNotSettled($transaction->fundCycle);

        DB::transaction(function () use ($transaction, $by): void {
            $this->ledger->lock('cycle:'.$transaction->fund_cycle_id);
            $this->ledger->reverseSource($transaction, "Cycle transaction #{$transaction->id} removed", $by);

            if ($this->ledger->balance(Account::Bank, ['fund_cycle_id' => $transaction->fund_cycle_id]) < 0) {
                throw ValidationException::withMessages([
                    'transaction' => 'Removing this income would leave the cycle bank balance negative.',
                ]);
            }
        });
    }

    /**
     * Full picture of a cycle, used for the settlement preview and reports.
     *
     * @return array{
     *     capital: int,
     *     cash: int,
     *     deployed: int,
     *     investments: list<array{id: int, type: string, title: string, status: string, result: int}>,
     *     investments_result: int,
     *     cycle_income: int,
     *     cycle_expense: int,
     *     result: int,
     *     members: list<array{member_id: int, user_id: int, name: string, capital: int, share: int, payout: int}>,
     *     blockers: list<string>,
     *     is_settled: bool,
     * }
     */
    public function summary(FundCycle $cycle): array
    {
        $cycleDims = ['fund_cycle_id' => $cycle->id];
        $isSettled = $cycle->settled_at !== null;

        $investments = CycleInvestment::query()
            ->where('fund_cycle_id', $cycle->id)
            ->orderBy('id')
            ->get()
            ->map(fn (CycleInvestment $investment): array => [
                'id' => $investment->id,
                'type' => $investment->type,
                'title' => $investment->title,
                'status' => $investment->status,
                'result' => $isSettled
                    ? $this->settledInvestmentResult($investment)
                    : $this->investments->result($investment),
            ])
            ->values()
            ->all();

        $cycleIncome = $this->cycleLevelBalance($cycle, Account::CycleIncome, $isSettled);
        $cycleExpense = -$this->cycleLevelBalance($cycle, Account::CycleExpense, $isSettled);
        $investmentsResult = array_sum(array_column($investments, 'result'));
        $result = $investmentsResult + $cycleIncome - $cycleExpense;

        $capitalByMember = $isSettled
            ? $this->settledCapitalByMember($cycle)
            : $this->ledger->balancesBy('member_id', Account::CycleCapital, $cycleDims)->map(fn (int $balance): int => -$balance)->filter();

        $members = $this->distribute($result, $capitalByMember);

        $blockers = [];

        if ($isSettled) {
            $blockers[] = 'This cycle is already settled.';
        }

        foreach ($investments as $investment) {
            if ($investment['status'] !== CycleInvestment::STATUS_CLOSED) {
                $blockers[] = "\"{$investment['title']}\" is not closed yet.";
            }
        }

        if ($capitalByMember->isEmpty() && ! $isSettled) {
            $blockers[] = 'No member capital is allocated to this cycle.';
        }

        $capital = (int) $capitalByMember->sum();
        $cash = $this->ledger->balance(Account::Bank, $cycleDims);

        if (! $isSettled && $blockers === [] && $cash !== $capital + $result) {
            $blockers[] = sprintf(
                'Cycle bank money (%s BDT) does not match capital + result (%s BDT). Check unclosed cash, bKash or business capital.',
                Money::format($cash),
                Money::format($capital + $result),
            );
        }

        return [
            'capital' => $capital,
            'cash' => $cash,
            'deployed' => $this->ledger->balance([Account::Bkash, Account::EventCash, Account::BusinessInvestment], $cycleDims),
            'investments' => $investments,
            'investments_result' => $investmentsResult,
            'cycle_income' => $cycleIncome,
            'cycle_expense' => $cycleExpense,
            'result' => $result,
            'members' => $members,
            'blockers' => $blockers,
            'is_settled' => $isSettled,
        ];
    }

    public function settle(FundCycle $cycle, ?User $by = null): void
    {
        DB::transaction(function () use ($cycle, $by): void {
            $this->ledger->lock('cycle:'.$cycle->id);
            $cycle->refresh();

            $summary = $this->summary($cycle);

            if ($summary['blockers'] !== []) {
                throw ValidationException::withMessages(['settle' => $summary['blockers']]);
            }

            $cycleDims = ['fund_cycle_id' => $cycle->id];
            $entry = $this->ledger->entry('cycle_settled', "Settle {$cycle->name}")
                ->source($cycle)
                ->key('cycle-settle:'.$cycle->id)
                ->by($by);

            // 1. Close cycle-level income/expense (their net flows into member payouts).
            $entry->signed(Account::CycleIncome, -$this->ledger->balance(Account::CycleIncome, $cycleDims), $cycleDims);
            $entry->signed(Account::CycleExpense, -$this->ledger->balance(Account::CycleExpense, $cycleDims), $cycleDims);

            // 2. Clear each investment's result, member capital; credit member balances.
            $resultByInvestment = $this->ledger->balancesBy('cycle_investment_id', Account::CycleResult, $cycleDims);

            foreach ($resultByInvestment as $investmentId => $balance) {
                $entry->signed(Account::CycleResult, -$balance, [...$cycleDims, 'cycle_investment_id' => $investmentId ?: null]);
            }

            foreach ($summary['members'] as $member) {
                $memberDims = ['user_id' => $member['user_id'], 'member_id' => $member['member_id']];

                $entry->debit(Account::CycleCapital, $member['capital'], [...$memberDims, ...$cycleDims]);
                $entry->signed(Account::MemberBalance, -$member['payout'], $memberDims, "capital + share from {$cycle->name}");
            }

            // 3. Release the cycle's bank earmark back to the general pool.
            $entry->debit(Account::Bank, $summary['cash'], ['fund_cycle_id' => null], 'release cycle earmark');
            $entry->credit(Account::Bank, $summary['cash'], $cycleDims, 'release cycle earmark');

            $entry->post();

            $cycle->update([
                'status' => FundCycle::STATUS_SETTLED,
                'settled_at' => now(),
                'settled_by_user_id' => $by?->id,
            ]);
        });
    }

    /**
     * Split the result by capital ratio. Leftover paisa go to the largest holder.
     *
     * @param  Collection<int|string, int>  $capitalByMember
     * @return list<array{member_id: int, user_id: int, name: string, capital: int, share: int, payout: int}>
     */
    public function distribute(int $result, Collection $capitalByMember): array
    {
        $totalCapital = (int) $capitalByMember->sum();

        if ($totalCapital <= 0) {
            return [];
        }

        $members = Member::query()
            ->whereIn('id', $capitalByMember->keys()->all())
            ->get(['id', 'full_name', 'managed_by_user_id'])
            ->keyBy('id');

        $rows = $capitalByMember
            ->map(function (int $capital, int|string $memberId) use ($result, $totalCapital, $members): array {
                $member = $members->get((int) $memberId);

                return [
                    'member_id' => (int) $memberId,
                    'user_id' => (int) $member?->managed_by_user_id,
                    'name' => (string) $member?->full_name,
                    'capital' => $capital,
                    'share' => (int) bcdiv(bcmul((string) $result, (string) $capital), (string) $totalCapital, 0),
                    'payout' => 0,
                ];
            })
            ->sortByDesc('capital')
            ->values()
            ->all();

        $rows[0]['share'] += $result - array_sum(array_column($rows, 'share'));

        foreach ($rows as $index => $row) {
            $rows[$index]['payout'] = $row['capital'] + $row['share'];
        }

        return $rows;
    }

    private function ensureNotSettled(?FundCycle $cycle): void
    {
        abort_if($cycle?->settled_at !== null, 403, 'This fund cycle has been settled and is locked for changes.');
    }

    private function cycleLevelBalance(FundCycle $cycle, Account $account, bool $isSettled): int
    {
        $query = JournalLine::query()
            ->where('ledger_account_id', LedgerAccount::idFor($account))
            ->where('fund_cycle_id', $cycle->id)
            ->whereNull('cycle_investment_id');

        if ($isSettled) {
            $query->whereHas('entry', fn ($entry) => $entry->where('kind', '!=', 'cycle_settled'));
        }

        return (int) $query->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')->value('balance');
    }

    private function settledInvestmentResult(CycleInvestment $investment): int
    {
        return (int) JournalLine::query()
            ->where('ledger_account_id', LedgerAccount::idFor(Account::CycleResult))
            ->where('cycle_investment_id', $investment->id)
            ->whereHas('entry', fn ($entry) => $entry->where('kind', 'investment_closed'))
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
            ->value('balance');
    }

    /**
     * @return Collection<int|string, int>
     */
    private function settledCapitalByMember(FundCycle $cycle): Collection
    {
        return JournalLine::query()
            ->where('ledger_account_id', LedgerAccount::idFor(Account::CycleCapital))
            ->where('fund_cycle_id', $cycle->id)
            ->whereHas('entry', fn ($entry) => $entry->where('kind', '!=', 'cycle_settled'))
            ->selectRaw('member_id')
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
            ->groupBy('member_id')
            ->pluck('balance', 'member_id')
            ->map(fn ($balance): int => (int) $balance)
            ->filter();
    }
}
