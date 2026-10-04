<?php

namespace App\Ledger;

use App\Ledger\Postings\InvestmentPostings;
use App\Models\CycleInvestment;
use App\Models\CycleInvestmentCharge;

/**
 * Ledger view of one sub-business (event or business), for the admin UI.
 * Amounts are in taka.
 */
class InvestmentReport
{
    public function __construct(private readonly InvestmentPostings $postings) {}

    /**
     * @return array<string, mixed>
     */
    public function for(CycleInvestment $investment): array
    {
        $profitAndLoss = $this->postings->profitAndLoss($investment);

        $lines = collect($profitAndLoss)
            ->map(function (int $creditBalance, string $code): array {
                $account = Account::from($code);
                $isIncome = $account->type() === 'income';

                return [
                    'code' => $code,
                    'label' => $account->label(),
                    'kind' => $isIncome ? 'income' : 'expense',
                    'amount' => Money::toTaka($isIncome ? $creditBalance : -$creditBalance),
                ];
            })
            ->sortBy('code')
            ->values();

        $totalIncome = round($lines->where('kind', 'income')->sum('amount'), 2);
        $totalExpense = round($lines->where('kind', 'expense')->sum('amount'), 2);

        return [
            'id' => $investment->id,
            'type' => $investment->type,
            'title' => $investment->title,
            'status' => $investment->status,
            'is_closed' => $investment->isClosed(),
            'is_cancelled' => $investment->isCancelled(),
            'closed_at' => $investment->closed_at?->format('d M Y, h:i A'),
            'lines' => $lines->all(),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'result' => Money::toTaka($this->postings->result($investment)),
            'cash' => Money::toTaka($this->postings->cash($investment)),
            'bkash' => Money::toTaka($this->postings->bkash($investment)),
            'invested_capital' => Money::toTaka($this->postings->outstandingCapital($investment)),
            'cycle_cash' => Money::toTaka($this->postings->cycleCash((int) $investment->fund_cycle_id)),
            'close_blockers' => $investment->isActive() ? $this->postings->closeBlockers($investment) : [],
            'cancel_blockers' => $investment->isActive() ? $this->postings->cancelBlockers($investment) : [],
            'charges' => $investment->charges()
                ->with('createdBy:id,name')
                ->orderByDesc('charged_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (CycleInvestmentCharge $charge): array => [
                    'id' => $charge->id,
                    'type' => $charge->type,
                    'type_label' => CycleInvestmentCharge::typeLabel($charge->type),
                    'amount' => (float) $charge->amount,
                    'note' => $charge->note,
                    'charged_at' => $charge->charged_at?->format('Y-m-d'),
                    'created_by_name' => $charge->createdBy?->name,
                ])
                ->values()
                ->all(),
            'charge_types' => collect(CycleInvestmentCharge::TYPES)
                ->map(fn (string $type): array => ['value' => $type, 'label' => CycleInvestmentCharge::typeLabel($type)])
                ->all(),
        ];
    }
}
