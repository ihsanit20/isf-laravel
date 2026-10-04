<?php

namespace App\Services;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;

class FundCycleWithdrawalBudgetService
{
    public function __construct(private readonly Ledger $ledger) {}

    /**
     * Cycle money position from the journal. `remaining_amount` is what the
     * cycle still holds in the bank — returned capital counts again.
     *
     * @return array{
     *     allocated_amount: int,
     *     withdrawn_amount: int,
     *     remaining_amount: int,
     * }
     */
    public function forCycle(int $fundCycleId): array
    {
        $dims = ['fund_cycle_id' => $fundCycleId];
        $allocated = $this->ledger->creditBalance(Account::CycleCapital, $dims);
        $remaining = $this->ledger->balance(Account::Bank, $dims);

        return [
            'allocated_amount' => (int) Money::toTaka($allocated),
            'withdrawn_amount' => (int) Money::toTaka($this->ledger->balance(
                [Account::Bkash, Account::EventCash, Account::BusinessInvestment],
                $dims,
            )),
            'remaining_amount' => (int) Money::toTaka($remaining),
        ];
    }
}
