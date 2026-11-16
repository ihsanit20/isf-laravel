<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Account;
use App\Ledger\InvestmentReport;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\BusinessSubEntry;
use App\Models\BusinessTransaction;
use App\Models\CycleInvestment;
use App\Models\FundCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Business investments: a sub-business of a fund cycle (mudaraba capital
 * placed with an outside business).
 */
class BusinessInvestmentController extends Controller
{
    public function __construct(
        private readonly InvestmentPostings $postings,
        private readonly InvestmentReport $report,
        private readonly Ledger $ledger,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/BusinessInvestments', [
            'investments' => CycleInvestment::query()
                ->where('type', CycleInvestment::TYPE_BUSINESS)
                ->with('fundCycle:id,name,status')
                ->latest('id')
                ->get()
                ->map(fn (CycleInvestment $investment): array => [
                    'id' => $investment->id,
                    'title' => $investment->title,
                    'counterparty' => $investment->counterparty,
                    'status' => $investment->status,
                    'invested_at' => $investment->invested_at?->format('Y-m-d'),
                    'fund_cycle' => ['id' => $investment->fund_cycle_id, 'name' => $investment->fundCycle?->name],
                    'invested_capital' => $this->postings->outstandingCapital($investment) / 100,
                    'result' => $this->postings->result($investment) / 100,
                ])
                ->values(),
            'fundCycles' => FundCycle::query()
                ->whereNull('settled_at')
                ->orderByDesc('start_date')
                ->get(['id', 'name'])
                ->map(fn (FundCycle $cycle): array => ['value' => (string) $cycle->id, 'label' => $cycle->name])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'fund_cycle_id' => ['required', 'integer', Rule::exists('fund_cycles', 'id')->whereNull('settled_at')],
            'title' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'invested_at' => ['nullable', 'date'],
        ]);

        $investment = CycleInvestment::query()->create([
            ...$attributes,
            'type' => CycleInvestment::TYPE_BUSINESS,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.businesses.show', $investment);
    }

    public function show(CycleInvestment $cycleInvestment): Response
    {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);
        $cycleInvestment->load('fundCycle:id,name,status,settled_at');

        return Inertia::render('admin/BusinessInvestmentDetails', [
            'investment' => [
                'id' => $cycleInvestment->id,
                'title' => $cycleInvestment->title,
                'counterparty' => $cycleInvestment->counterparty,
                'terms' => $cycleInvestment->terms,
                'invested_at' => $cycleInvestment->invested_at?->format('Y-m-d'),
                'status' => $cycleInvestment->status,
                'fund_cycle' => ['id' => $cycleInvestment->fund_cycle_id, 'name' => $cycleInvestment->fundCycle?->name],
            ],
            'ledger' => $this->report->for($cycleInvestment),
            'transactions' => $cycleInvestment->businessTransactions()
                ->with('createdBy:id,name')
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BusinessTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'type_label' => BusinessTransaction::typeLabel($transaction->type),
                    'amount' => (float) $transaction->amount,
                    'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
                    'description' => $transaction->description,
                    'reference_no' => $transaction->reference_no,
                    'created_by_name' => $transaction->createdBy?->name,
                ])
                ->values(),
            'transactionTypes' => collect(BusinessTransaction::TYPES)
                ->map(fn (string $type): array => ['value' => $type, 'label' => BusinessTransaction::typeLabel($type)])
                ->values(),
            ...$this->subLedgerProps($cycleInvestment),
        ]);
    }

    /**
     * Note-only operator records. Summed from their own table on purpose:
     * they are not journal money and never affect ISF balances.
     *
     * @return array{subEntries: mixed, subSummary: array<string, mixed>, subCategories: mixed}
     */
    private function subLedgerProps(CycleInvestment $investment): array
    {
        $entries = $investment->subEntries()
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $totalExpense = (float) $entries->where('direction', BusinessSubEntry::DIRECTION_EXPENSE)->sum('amount');
        $totalIncome = (float) $entries->where('direction', BusinessSubEntry::DIRECTION_INCOME)->sum('amount');

        $capitalGiven = Money::toTaka(
            $this->ledger->movementsByKind(Account::BusinessInvestment, $investment->dimensions())->get('business_invest', 0),
        );

        return [
            'subEntries' => $entries
                ->map(fn (BusinessSubEntry $entry): array => [
                    'id' => $entry->id,
                    'direction' => $entry->direction,
                    'category' => $entry->category,
                    'amount' => (float) $entry->amount,
                    'entry_date' => $entry->entry_date?->format('Y-m-d'),
                    'note' => $entry->note,
                ])
                ->values(),
            'subSummary' => [
                'total_expense' => $totalExpense,
                'total_income' => $totalIncome,
                'result' => round($totalIncome - $totalExpense, 2),
                'capital_given' => $capitalGiven,
                'unspent' => round($capitalGiven - $totalExpense, 2),
                'by_category' => $entries
                    ->groupBy(fn (BusinessSubEntry $entry): string => $entry->direction.'|'.$entry->category)
                    ->map(fn ($group): array => [
                        'direction' => $group->first()->direction,
                        'category' => $group->first()->category,
                        'total' => (float) $group->sum('amount'),
                        'count' => $group->count(),
                    ])
                    ->sortBy([['direction', 'asc'], ['total', 'desc']])
                    ->values(),
            ],
            'subCategories' => BusinessSubEntry::query()
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ];
    }

    public function update(Request $request, CycleInvestment $cycleInvestment): RedirectResponse
    {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);
        $cycleInvestment->ensureActive();

        $cycleInvestment->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'invested_at' => ['nullable', 'date'],
        ]));

        return back();
    }

    public function storeTransaction(Request $request, CycleInvestment $cycleInvestment): RedirectResponse
    {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);
        $cycleInvestment->ensureActive();

        $attributes = $request->validate([
            'type' => ['required', 'string', Rule::in(BusinessTransaction::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_no' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($request, $cycleInvestment, $attributes): void {
            $transaction = $cycleInvestment->businessTransactions()->create([
                ...$attributes,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->postings->businessTransaction($transaction, $request->user());
        });

        return back();
    }

    public function destroyTransaction(
        Request $request,
        CycleInvestment $cycleInvestment,
        BusinessTransaction $transaction,
    ): RedirectResponse {
        abort_unless($transaction->cycle_investment_id === $cycleInvestment->id, 404);
        $cycleInvestment->ensureActive();

        DB::transaction(function () use ($request, $cycleInvestment, $transaction): void {
            $this->ledger->lock('cycle:'.$cycleInvestment->fund_cycle_id, 'investment:'.$cycleInvestment->id);
            $this->postings->removed($transaction, $request->user());
            $transaction->delete();

            if ($this->postings->outstandingCapital($cycleInvestment) < 0
                || $this->ledger->balance(Account::Bank, ['fund_cycle_id' => $cycleInvestment->fund_cycle_id]) < 0) {
                throw ValidationException::withMessages([
                    'transaction' => 'Removing this entry would leave a negative balance. Remove the later entries first.',
                ]);
            }
        });

        return back();
    }

    public function close(Request $request, CycleInvestment $cycleInvestment): RedirectResponse
    {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);

        $this->postings->close($cycleInvestment, $request->user());

        return back();
    }

    public function cancel(Request $request, CycleInvestment $cycleInvestment): RedirectResponse
    {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);

        $this->postings->cancel($cycleInvestment, $request->user());

        return back();
    }
}
