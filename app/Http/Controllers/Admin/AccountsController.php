<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\CyclePostings;
use App\Models\FundCycle;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\TreasuryBalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal-based reports: trial balance, platform P&L, cycle positions, journal.
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

    public function journal(Request $request): Response
    {
        $kind = $request->string('kind')->toString();
        $accountCode = $request->string('account')->toString();
        $cycleId = $request->integer('cycle') ?: null;
        $userId = $request->integer('user') ?: null;
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();

        $entries = JournalEntry::query()
            ->with(['lines.ledgerAccount:id,code,name', 'lines.user:id,name', 'lines.member:id,full_name', 'lines.fundCycle:id,name', 'lines.cycleInvestment:id,title', 'postedBy:id,name'])
            ->when($kind !== '', fn ($query) => $query->where('kind', 'like', $kind.'%'))
            ->when($fromDate !== '', fn ($query) => $query->whereDate('entry_date', '>=', $fromDate))
            ->when($toDate !== '', fn ($query) => $query->whereDate('entry_date', '<=', $toDate))
            ->when($accountCode !== '' || $cycleId || $userId, fn ($query) => $query->whereHas('lines', function ($lines) use ($accountCode, $cycleId, $userId): void {
                $lines
                    ->when($accountCode !== '', fn ($q) => $q->whereHas('ledgerAccount', fn ($a) => $a->where('code', $accountCode)))
                    ->when($cycleId, fn ($q) => $q->where('fund_cycle_id', $cycleId))
                    ->when($userId, fn ($q) => $q->where('user_id', $userId));
            }))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (JournalEntry $entry): array => [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date?->format('Y-m-d'),
                'kind' => $entry->kind,
                'description' => $entry->description,
                'is_reversal' => $entry->reversal_of_id !== null,
                'reversal_of_id' => $entry->reversal_of_id,
                'posted_by' => $entry->postedBy?->name,
                'posted_at' => $entry->posted_at?->format('d M Y, h:i A'),
                'lines' => $entry->lines->map(fn (JournalLine $line): array => [
                    'code' => $line->ledgerAccount?->code,
                    'account' => $line->ledgerAccount?->name,
                    'debit' => Money::toTaka($line->debit),
                    'credit' => Money::toTaka($line->credit),
                    'dimensions' => array_values(array_filter([
                        $line->user?->name ? 'User: '.$line->user->name : null,
                        $line->member?->full_name ? 'Member: '.$line->member->full_name : null,
                        $line->fundCycle?->name ? 'Cycle: '.$line->fundCycle->name : null,
                        $line->cycleInvestment?->title ? 'Project: '.$line->cycleInvestment->title : null,
                        $line->event_order_id ? 'Order #'.$line->event_order_id : null,
                    ])),
                    'memo' => $line->memo,
                ])->values(),
            ]);

        return Inertia::render('admin/Journal', [
            'entries' => $entries,
            'filters' => [
                'kind' => $kind,
                'account' => $accountCode,
                'cycle' => $cycleId,
                'user' => $userId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'accounts' => collect(Account::cases())
                ->map(fn (Account $account): array => ['value' => $account->value, 'label' => $account->value.' · '.$account->label()])
                ->values(),
            'cycles' => FundCycle::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn (FundCycle $cycle): array => ['value' => (string) $cycle->id, 'label' => $cycle->name])
                ->values(),
        ]);
    }
}
