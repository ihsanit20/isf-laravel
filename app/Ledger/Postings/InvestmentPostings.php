<?php

namespace App\Ledger\Postings;

use App\Enums\EventExpenseCategory;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\BusinessTransaction;
use App\Models\CycleInvestment;
use App\Models\CycleInvestmentCharge;
use App\Models\EventBankDeposit;
use App\Models\EventBankWithdrawal;
use App\Models\EventExpense;
use App\Models\EventIncome;
use App\Models\EventPayment;
use App\Models\EventRefund;
use App\Models\FundCycleEvent;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sub-businesses (events and business investments) under a fund cycle.
 * Each has its own income/expense via the `cycle_investment_id` dimension.
 */
class InvestmentPostings
{
    public function __construct(private readonly Ledger $ledger) {}

    // ---------------------------------------------------------------- reads

    public function cycleCash(int $cycleId): int
    {
        return $this->ledger->balance(Account::Bank, ['fund_cycle_id' => $cycleId]);
    }

    public function cash(CycleInvestment $investment): int
    {
        return $this->ledger->balance(Account::EventCash, $investment->dimensions());
    }

    public function bkash(CycleInvestment $investment): int
    {
        return $this->ledger->balance(Account::Bkash, $investment->dimensions());
    }

    public function outstandingCapital(CycleInvestment $investment): int
    {
        return $this->ledger->balance(Account::BusinessInvestment, $investment->dimensions());
    }

    /**
     * Running profit (+) or loss (−) of an investment, in paisa.
     */
    public function result(CycleInvestment $investment): int
    {
        if ($investment->isClosed()) {
            // Read the closing entry, not the 2030 balance: settling the
            // cycle moves 2030 to members and would zero it out.
            return (int) JournalLine::query()
                ->where('ledger_account_id', LedgerAccount::idFor(Account::CycleResult))
                ->where('cycle_investment_id', $investment->id)
                ->whereHas('entry', fn ($entry) => $entry->where('kind', 'investment_closed'))
                ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
                ->value('balance');
        }

        return $this->ledger->creditBalance(Account::fundProfitAndLoss(), $investment->dimensions());
    }

    /**
     * @return array<string, int> credit-positive balance per fund P&L account code
     */
    public function profitAndLoss(CycleInvestment $investment): array
    {
        $dims = $investment->dimensions();

        if ($investment->isClosed()) {
            return $this->closedProfitAndLoss($investment);
        }

        return $this->ledger->balancesByAccount($dims)
            ->filter(fn (int $balance, string $code): bool => Account::from($code)->scope() === 'fund')
            ->map(fn (int $balance): int => -$balance)
            ->all();
    }

    /**
     * Where an event's money came from and went, in paisa, read from the
     * journal. Cash parts add up: withdrawn + cash_received − cash_spent −
     * cash_refunded − cash_deposited + cash_other = cash.
     *
     * @return array<string, int>
     */
    public function eventMoneyFlow(CycleInvestment $investment): array
    {
        $dims = $investment->dimensions();
        $cash = $this->ledger->movementsByKind(Account::EventCash, $dims);
        $bkash = $this->ledger->movementsByKind(Account::Bkash, $dims);
        $sales = $this->ledger->movementsByKind(Account::EventSales, $dims);
        $expenses = $this->ledger->movementsByKind([Account::SubBusinessExpense, Account::GatewayFee], $dims);

        $cashKinds = ['event_withdrawal', 'event_sale', 'event_other_income', 'event_expense', 'event_refund', 'event_bank_deposit'];

        return [
            'withdrawn' => $cash->get('event_withdrawal', 0),
            'cash_received' => $cash->get('event_sale', 0) + $cash->get('event_other_income', 0),
            'cash_spent' => -$cash->get('event_expense', 0),
            'cash_refunded' => -$cash->get('event_refund', 0),
            'cash_deposited' => -$cash->get('event_bank_deposit', 0),
            'cash_other' => (int) $cash->except($cashKinds)->sum(),
            'cash' => (int) $cash->sum(),
            'bank_deposited' => -($cash->get('event_bank_deposit', 0) + $bkash->get('event_bank_deposit', 0)),
            'sales' => -$sales->get('event_sale', 0),
            'expenses' => $expenses->get('event_expense', 0),
        ];
    }

    // ---------------------------------------------------------------- events

    public function eventWithdrawal(EventBankWithdrawal $withdrawal, ?User $by = null): void
    {
        $investment = $this->investmentForEvent($withdrawal->fund_cycle_event_id);
        $amount = Money::toPaisa($withdrawal->amount);

        $this->repost($withdrawal, 'event-withdrawal', $by, function (string $key) use ($withdrawal, $investment, $amount, $by): void {
            $this->lockAndAssertCycleCash($investment, $amount, 'amount');

            $this->ledger->entry('event_withdrawal', "Bank withdrawal for {$investment->title}")
                ->on($withdrawal->withdrawal_date)
                ->source($withdrawal)
                ->key($key)
                ->by($by)
                ->debit(Account::EventCash, $amount, $investment->dimensions())
                ->credit(Account::Bank, $amount, ['fund_cycle_id' => $investment->fund_cycle_id])
                ->post();
        });
    }

    public function eventExpense(EventExpense $expense, ?User $by = null): void
    {
        $investment = $this->investmentForEvent($expense->fund_cycle_event_id);
        $amount = Money::toPaisa($expense->amount);
        $account = $expense->category === EventExpenseCategory::PaymentFee
            ? Account::GatewayFee
            : Account::SubBusinessExpense;

        $this->repost($expense, 'event-expense', $by, function (string $key) use ($expense, $investment, $amount, $account, $by): void {
            if ($expense->paid_from === 'bank') {
                $this->lockAndAssertCycleCash($investment, $amount, 'amount');
            }

            $this->ledger->entry('event_expense', $expense->category->label()." expense for {$investment->title}")
                ->on($expense->expense_date)
                ->source($expense)
                ->key($key)
                ->by($by)
                ->debit($account, $amount, $investment->dimensions())
                ->credit(...$this->moneyAccount($expense->paid_from, $investment, $amount))
                ->post();
        });
    }

    public function eventIncome(EventIncome $income, ?User $by = null): void
    {
        $investment = $this->investmentForEvent($income->fund_cycle_event_id);
        $amount = Money::toPaisa($income->amount);

        $this->repost($income, 'event-income', $by, function (string $key) use ($income, $investment, $amount, $by): void {
            $this->ledger->entry('event_other_income', EventIncome::categoryLabel($income->category)." for {$investment->title}")
                ->on($income->income_date)
                ->source($income)
                ->key($key)
                ->by($by)
                ->debit(...$this->moneyAccount($income->received_via, $investment, $amount))
                ->credit(Account::SubBusinessOtherIncome, $amount, $investment->dimensions())
                ->post();
        });
    }

    public function eventBankDeposit(EventBankDeposit $deposit, ?User $by = null): void
    {
        $investment = $this->investmentForEvent($deposit->fund_cycle_event_id);
        $amount = Money::toPaisa($deposit->amount);
        $from = $deposit->source === 'bkash' ? Account::Bkash : Account::EventCash;

        $this->repost($deposit, 'event-bank-deposit', $by, function (string $key) use ($deposit, $investment, $amount, $from, $by): void {
            $this->ledger->entry('event_bank_deposit', ($deposit->source === 'bkash' ? 'bKash settlement' : 'Cash deposit')." for {$investment->title}")
                ->on($deposit->deposit_date)
                ->source($deposit)
                ->key($key)
                ->by($by)
                ->debit(Account::Bank, $amount, ['fund_cycle_id' => $investment->fund_cycle_id])
                ->credit($from, $amount, $investment->dimensions())
                ->post();
        });
    }

    /**
     * Cash basis: a verified customer payment is event revenue.
     */
    public function customerPaymentVerified(EventPayment $payment, ?User $by = null): void
    {
        $payment->loadMissing('order');
        $investment = $this->investmentForEvent($payment->order->fund_cycle_event_id);
        $amount = Money::toPaisa($payment->amount);
        $via = match ($payment->payment_method) {
            'bkash' => 'bkash',
            'bank' => 'bank',
            default => 'cash',
        };

        $this->ledger->entry('event_sale', "Payment for order {$payment->order->order_number}")
            ->on($payment->verified_at ?? now())
            ->source($payment)
            ->key('event-payment:'.$payment->id)
            ->by($by)
            ->debit(...$this->moneyAccount($via, $investment, $amount))
            ->credit(Account::EventSales, $amount, [...$investment->dimensions(), 'event_order_id' => $payment->event_order_id])
            ->post();
    }

    public function refund(EventRefund $refund, ?User $by = null): void
    {
        $refund->loadMissing('order');
        $investment = $this->investmentForEvent($refund->order->fund_cycle_event_id);
        $amount = Money::toPaisa($refund->amount);

        DB::transaction(function () use ($refund, $investment, $amount, $by): void {
            if ($refund->method === 'bank') {
                $this->lockAndAssertCycleCash($investment, $amount, 'amount');
            }

            $this->ledger->entry('event_refund', "Refund for order {$refund->order->order_number}")
                ->on($refund->refunded_at)
                ->source($refund)
                ->key('event-refund:'.$refund->id)
                ->by($by)
                ->debit(Account::EventSalesRefund, $amount, [...$investment->dimensions(), 'event_order_id' => $refund->event_order_id])
                ->credit(...$this->moneyAccount($refund->method, $investment, $amount))
                ->post();
        });
    }

    // ---------------------------------------------------------------- charges

    /**
     * Platform service charge, asset rent, etc. — an expense of the
     * sub-business and income of the platform. Money moves from the cycle's
     * share of the bank to the platform's share.
     */
    public function charge(CycleInvestmentCharge $charge, ?User $by = null): void
    {
        $charge->loadMissing('investment');
        $investment = $charge->investment;
        $amount = Money::toPaisa($charge->amount);
        [$expenseAccount, $incomeAccount] = $charge->accounts();

        DB::transaction(function () use ($charge, $investment, $amount, $expenseAccount, $incomeAccount, $by): void {
            $this->lockAndAssertCycleCash($investment, $amount, 'amount');

            $this->ledger->entry('platform_charge', CycleInvestmentCharge::typeLabel($charge->type)." on {$investment->title}")
                ->on($charge->charged_at)
                ->source($charge)
                ->key('investment-charge:'.$charge->id)
                ->by($by)
                ->debit($expenseAccount, $amount, $investment->dimensions())
                ->credit($incomeAccount, $amount, $investment->dimensions())
                ->debit(Account::Bank, $amount, ['fund_cycle_id' => null], 'to platform share')
                ->credit(Account::Bank, $amount, ['fund_cycle_id' => $investment->fund_cycle_id], 'to platform share')
                ->post();
        });
    }

    // ---------------------------------------------------------------- business

    public function businessTransaction(BusinessTransaction $transaction, ?User $by = null): void
    {
        $transaction->loadMissing('investment');
        $investment = $transaction->investment;
        $dims = $investment->dimensions();
        $cycleBank = ['fund_cycle_id' => $investment->fund_cycle_id];
        $amount = Money::toPaisa($transaction->amount);

        DB::transaction(function () use ($transaction, $investment, $dims, $cycleBank, $amount, $by): void {
            $this->ledger->lock('cycle:'.$investment->fund_cycle_id, 'investment:'.$investment->id);

            if (in_array($transaction->type, ['invest', 'expense'], true)) {
                $this->ledger->assertAvailable(Account::Bank, $cycleBank, $amount, 'amount', 'Not enough cycle money in the bank.');
            }

            if (in_array($transaction->type, ['capital_return', 'capital_loss'], true)) {
                $this->ledger->assertAvailable(Account::BusinessInvestment, $dims, $amount, 'amount', 'Amount exceeds the capital still invested.');
            }

            [$debit, $debitDims, $credit, $creditDims] = match ($transaction->type) {
                'invest' => [Account::BusinessInvestment, $dims, Account::Bank, $cycleBank],
                'profit' => [Account::Bank, $cycleBank, Account::BusinessProfit, $dims],
                'capital_return' => [Account::Bank, $cycleBank, Account::BusinessInvestment, $dims],
                'capital_loss' => [Account::BusinessCapitalLoss, $dims, Account::BusinessInvestment, $dims],
                'other_income' => [Account::Bank, $cycleBank, Account::SubBusinessOtherIncome, $dims],
                default => [Account::SubBusinessExpense, $dims, Account::Bank, $cycleBank],
            };

            $this->ledger->entry('business_'.$transaction->type, BusinessTransaction::typeLabel($transaction->type)." — {$investment->title}")
                ->on($transaction->transaction_date)
                ->source($transaction)
                ->key('business-transaction:'.$transaction->id)
                ->by($by)
                ->debit($debit, $amount, $debitDims)
                ->credit($credit, $amount, $creditDims)
                ->post();
        });
    }

    // ---------------------------------------------------------------- close

    /**
     * Problems that block closing. Empty list means ready.
     *
     * @return list<string>
     */
    public function closeBlockers(CycleInvestment $investment): array
    {
        $blockers = [];

        if ($investment->isClosed()) {
            return ['Already closed.'];
        }

        if ($investment->isEvent()) {
            $event = $investment->event;

            if ($event !== null && $event->orders()
                ->whereHas('payments', fn ($query) => $query->where('payment_status', 'pending'))
                ->exists()) {
                $blockers[] = 'Some orders still have pending payments. Verify or reject them first.';
            }
        }

        if (($cash = $this->cash($investment)) !== 0) {
            $blockers[] = sprintf('Event cash / float is %s BDT. Deposit it to the bank (or log the expenses) first.', Money::format($cash));
        }

        if (($bkash = $this->bkash($investment)) !== 0) {
            $blockers[] = sprintf('bKash wallet holds %s BDT for this event. Record the bKash settlement to bank (and gateway fee) first.', Money::format($bkash));
        }

        if (($capital = $this->outstandingCapital($investment)) !== 0) {
            $blockers[] = sprintf('%s BDT of capital is still invested. Record the capital return or loss first.', Money::format($capital));
        }

        return $blockers;
    }

    public function close(CycleInvestment $investment, ?User $by = null): void
    {
        DB::transaction(function () use ($investment, $by): void {
            $this->ledger->lock('investment:'.$investment->id);
            $investment->refresh();

            $blockers = $this->closeBlockers($investment);

            if ($blockers !== []) {
                throw ValidationException::withMessages(['close' => $blockers]);
            }

            $dims = $investment->dimensions();
            $balances = $this->ledger->balancesByAccount($dims)
                ->filter(fn (int $balance, string $code): bool => $balance !== 0 && Account::from($code)->scope() === 'fund');

            $entry = $this->ledger->entry('investment_closed', "Close {$investment->title}")
                ->source($investment)
                ->key('investment-close:'.$investment->id)
                ->by($by);

            foreach ($balances as $code => $balance) {
                $entry->signed(Account::from($code), -$balance, $dims);
            }

            $entry->signed(Account::CycleResult, $balances->sum(), $dims, 'result of '.$investment->title);

            if ($entry->hasLines()) {
                $entry->post();
            }

            $investment->update([
                'status' => CycleInvestment::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $by?->id,
            ]);
        });
    }

    // ---------------------------------------------------------------- helpers

    public function investmentForEvent(int $eventId): CycleInvestment
    {
        return FundCycleEvent::query()->findOrFail($eventId)->ensureInvestment();
    }

    /**
     * Reverse previous postings of an editable source, then post again.
     */
    public function repost(Model $source, string $prefix, ?User $by, callable $post): void
    {
        DB::transaction(function () use ($source, $prefix, $by, $post): void {
            $this->ledger->reverseSource($source, class_basename($source)." #{$source->getKey()} updated", $by);
            $post($this->ledger->versionedKey($prefix.':'.$source->getKey(), $source));
        });
    }

    public function removed(Model $source, ?User $by = null): void
    {
        $this->ledger->reverseSource($source, class_basename($source)." #{$source->getKey()} removed", $by);
    }

    /**
     * @return array{0: Account, 1: int, 2: array<string, int|null>}
     */
    private function moneyAccount(string $via, CycleInvestment $investment, int $amount): array
    {
        return match ($via) {
            'bkash' => [Account::Bkash, $amount, $investment->dimensions()],
            'bank' => [Account::Bank, $amount, ['fund_cycle_id' => $investment->fund_cycle_id]],
            default => [Account::EventCash, $amount, $investment->dimensions()],
        };
    }

    private function lockAndAssertCycleCash(CycleInvestment $investment, int $amount, string $field): void
    {
        $investment->ensureActive();
        $this->ledger->lock('cycle:'.$investment->fund_cycle_id);
        $this->ledger->assertAvailable(
            Account::Bank,
            ['fund_cycle_id' => $investment->fund_cycle_id],
            $amount,
            $field,
            'Not enough cycle money in the bank.',
        );
    }

    /**
     * @return array<string, int>
     */
    private function closedProfitAndLoss(CycleInvestment $investment): array
    {
        $closingEntryIds = JournalEntry::query()
            ->where('idempotency_key', 'investment-close:'.$investment->id)
            ->pluck('id');

        return JournalLine::query()
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_lines.cycle_investment_id', $investment->id)
            ->whereNotIn('journal_lines.journal_entry_id', $closingEntryIds)
            ->where('ledger_accounts.scope', 'fund')
            ->selectRaw('ledger_accounts.code as code')
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
            ->groupBy('ledger_accounts.code')
            ->pluck('balance', 'code')
            ->map(fn ($balance): int => (int) $balance)
            ->filter(fn (int $balance): bool => $balance !== 0)
            ->all();
    }
}
