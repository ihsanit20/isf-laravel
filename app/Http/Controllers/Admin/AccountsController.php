<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\CyclePostings;
use App\Models\FundCycle;
use App\Services\TreasuryBalanceService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal-based reports: trial balance, platform P&L, cycle positions.
 */
class AccountsController extends Controller
{
    public function __construct(private readonly Ledger $ledger) {}

    public function index(TreasuryBalanceService $treasury, CyclePostings $cycles): Response
    {
        $balances = $this->ledger->balancesByAccount();

        $trialBalance = collect(Account::cases())
            ->map(function (Account $account) use ($balances): array {
                $balance = (int) ($balances[$account->value] ?? 0);

                return [
                    'code' => $account->value,
                    'name' => $account->label(),
                    'type' => $account->type(),
                    'scope' => $account->scope(),
                    'debit' => $balance > 0 ? Money::toTaka($balance) : 0,
                    'credit' => $balance < 0 ? Money::toTaka(-$balance) : 0,
                ];
            })
            ->filter(fn (array $row): bool => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        $platformLines = collect(Account::cases())
            ->filter(fn (Account $account): bool => $account->scope() === 'platform')
            ->map(fn (Account $account): array => [
                'code' => $account->value,
                'name' => $account->label(),
                'type' => $account->type(),
                'amount' => Money::toTaka(abs((int) ($balances[$account->value] ?? 0))),
            ])
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->values();

        return Inertia::render('admin/Accounts', [
            'treasury' => $treasury->summary(),
            'trialBalance' => $trialBalance,
            'trialTotals' => [
                'debit' => round($trialBalance->sum('debit'), 2),
                'credit' => round($trialBalance->sum('credit'), 2),
            ],
            'platform' => [
                'lines' => $platformLines,
                'income' => round($platformLines->where('type', 'income')->sum('amount'), 2),
                'expense' => round($platformLines->where('type', 'expense')->sum('amount'), 2),
                'fund' => Money::toTaka($this->ledger->platformFund()),
            ],
            'cycles' => FundCycle::query()
                ->orderByDesc('start_date')
                ->get()
                ->map(function (FundCycle $cycle) use ($cycles): array {
                    $summary = $cycles->summary($cycle);

                    return [
                        'id' => $cycle->id,
                        'name' => $cycle->name,
                        'status' => $cycle->status,
                        'is_settled' => $summary['is_settled'],
                        'capital' => Money::toTaka($summary['capital']),
                        'cash' => Money::toTaka($summary['cash']),
                        'deployed' => Money::toTaka($summary['deployed']),
                        'result' => Money::toTaka($summary['result']),
                    ];
                })
                ->values(),
        ]);
    }
}
