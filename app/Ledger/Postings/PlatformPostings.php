<?php

namespace App\Ledger\Postings;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\GeneralExpense;
use App\Models\GeneralIncome;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The platform's own income and expense. Never touches member money:
 * an expense is refused when the platform's own fund cannot cover it.
 */
class PlatformPostings
{
    public function __construct(private readonly Ledger $ledger) {}

    public function fund(): int
    {
        return $this->ledger->platformFund();
    }

    public function incomeRecorded(GeneralIncome $income, ?User $by = null): void
    {
        DB::transaction(function () use ($income, $by): void {
            $this->ledger->reverseSource($income, "General income #{$income->id} updated", $by);

            $amount = Money::toPaisa($income->amount);

            $this->ledger->entry('platform_income', $income->category->label().' (general income)')
                ->on($income->income_date)
                ->source($income)
                ->key($this->ledger->versionedKey('general-income:'.$income->id, $income))
                ->by($by)
                ->debit(Account::Bank, $amount, ['fund_cycle_id' => null])
                ->credit($income->category->ledgerAccount(), $amount)
                ->post();
        });
    }

    public function expenseRecorded(GeneralExpense $expense, ?User $by = null): void
    {
        DB::transaction(function () use ($expense, $by): void {
            $this->ledger->lock('platform');
            $this->ledger->reverseSource($expense, "General expense #{$expense->id} updated", $by);

            $amount = Money::toPaisa($expense->amount);
            $available = $this->fund();

            if ($amount > $available) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Platform fund is not enough for this expense (available: %s BDT). Member money cannot be used for platform expenses.',
                        number_format($available / 100, 2),
                    ),
                ]);
            }

            $this->ledger->entry('platform_expense', $expense->category->label().' (general expense)')
                ->on($expense->expense_date)
                ->source($expense)
                ->key($this->ledger->versionedKey('general-expense:'.$expense->id, $expense))
                ->by($by)
                ->debit($expense->category->ledgerAccount(), $amount)
                ->credit(Account::Bank, $amount, ['fund_cycle_id' => null])
                ->post();
        });
    }
}
