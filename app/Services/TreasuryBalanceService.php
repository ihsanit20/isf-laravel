<?php

namespace App\Services;

use App\Enums\DepositSubmissionStatus;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\DepositSubmission;

class TreasuryBalanceService
{
    public function __construct(private readonly Ledger $ledger) {}

    /**
     * Joint bank position from the journal, split by who the money belongs to.
     * Amounts are in taka.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $balances = $this->ledger->balancesByAccount();
        $paisa = fn (Account ...$accounts): int => array_sum(array_map(
            fn (Account $account): int => (int) ($balances[$account->value] ?? 0),
            $accounts,
        ));
        $debit = fn (Account ...$accounts): float => Money::toTaka($paisa(...$accounts));
        $credit = fn (Account ...$accounts): float => Money::toTaka(-$paisa(...$accounts));

        $platformIncome = array_values(array_filter(Account::cases(), fn (Account $a) => $a->scope() === 'platform' && $a->type() === 'income'));
        $platformExpense = array_values(array_filter(Account::cases(), fn (Account $a) => $a->scope() === 'platform' && $a->type() === 'expense'));

        $pending = DepositSubmission::query()->where('status', DepositSubmissionStatus::Pending);

        return [
            'bank_balance' => $debit(Account::Bank),
            'bkash_balance' => $debit(Account::Bkash),
            'event_cash' => $debit(Account::EventCash),
            'business_investment' => $debit(Account::BusinessInvestment),
            'members_available' => $credit(Account::MemberBalance),
            'cycle_capital' => $credit(Account::CycleCapital),
            'cycle_results' => $credit(Account::CycleResult),
            'platform_fund' => Money::toTaka($this->ledger->platformFund()),
            'platform_income' => $credit(...$platformIncome),
            'platform_expense' => $debit(...$platformExpense),
            'fee_income' => $credit(Account::RegistrationFeeIncome, Account::OtherFeeIncome),
            'charge_income' => $credit(Account::PlatformServiceIncome, Account::AssetRentIncome, Account::OtherChargeIncome),
            'verified_amount' => Money::toTaka(app(MemberPostings::class)->balanceBreakdown()['deposits']),
            'rejected_amount' => round((float) DepositSubmission::query()->where('status', DepositSubmissionStatus::Rejected)->sum('amount'), 2),
            'pending_amount' => round((float) (clone $pending)->sum('amount'), 2),
            'pending_count' => (int) (clone $pending)->count(),
        ];
    }
}
