<?php

namespace App\Http\Controllers;

use App\Ledger\Account;
use App\Ledger\Money;
use App\Ledger\Postings\CyclePostings;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\CycleInvestment;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyFundCycleController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $myAllocatedTotals = FundCycleAllocation::query()
            ->whereHas('member', fn ($query) => $query->where('managed_by_user_id', $user->id))
            ->selectRaw('fund_cycle_id, SUM(amount) as total')
            ->groupBy('fund_cycle_id')
            ->pluck('total', 'fund_cycle_id');

        return Inertia::render('FundCycles', [
            'fundCycles' => FundCycle::query()
                ->where('status', '!=', FundCycle::STATUS_DRAFT)
                ->withCount('allocations')
                ->withSum('allocations', 'amount')
                ->latest('start_date')
                ->latest('id')
                ->get()
                ->map(fn (FundCycle $fundCycle): array => [
                    'id' => $fundCycle->id,
                    'name' => $fundCycle->name,
                    'status' => $fundCycle->status,
                    'status_label' => FundCycle::statusLabel($fundCycle->status),
                    'unit_amount' => $fundCycle->unit_amount,
                    'start_date' => $fundCycle->start_date?->format('d M Y'),
                    'lock_date' => $fundCycle->lock_date?->format('d M Y'),
                    'maturity_date' => $fundCycle->maturity_date?->format('d M Y'),
                    'settlement_date' => $fundCycle->settlement_date?->format('d M Y'),
                    'allocations_count' => (int) $fundCycle->allocations_count,
                    'total_allocated_amount' => (int) ($fundCycle->allocations_sum_amount ?? 0),
                    'my_allocated_amount' => (int) ($myAllocatedTotals[$fundCycle->id] ?? 0),
                ])
                ->values(),
        ]);
    }

    public function show(
        Request $request,
        FundCycle $fundCycle,
        CyclePostings $cyclePostings,
        InvestmentPostings $investmentPostings,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $fundCycle->loadCount('allocations')->loadSum('allocations', 'amount');

        $myAllocatedAmount = (int) FundCycleAllocation::query()
            ->where('fund_cycle_id', $fundCycle->id)
            ->whereHas('member', fn ($query) => $query->where('managed_by_user_id', $user->id))
            ->sum('amount');

        $cycleSummary = $cyclePostings->summary($fundCycle);
        $closedInvestments = CycleInvestment::query()
            ->where('fund_cycle_id', $fundCycle->id)
            ->where('status', CycleInvestment::STATUS_CLOSED)
            ->orderBy('closed_at')
            ->get();
        $myRows = collect($cycleSummary['members'])->where('user_id', $user->id);

        return Inertia::render('FundCycleDetails', [
            'fundCycle' => [
                'id' => $fundCycle->id,
                'name' => $fundCycle->name,
                'status' => $fundCycle->status,
                'status_label' => FundCycle::statusLabel($fundCycle->status),
                'unit_amount' => $fundCycle->unit_amount,
                'start_date' => $fundCycle->start_date?->format('d M Y'),
                'lock_date' => $fundCycle->lock_date?->format('d M Y'),
                'maturity_date' => $fundCycle->maturity_date?->format('d M Y'),
                'settlement_date' => $fundCycle->settlement_date?->format('d M Y'),
                'allocations_count' => (int) $fundCycle->allocations_count,
                'total_allocated_amount' => (int) ($fundCycle->allocations_sum_amount ?? 0),
                'my_allocated_amount' => $myAllocatedAmount,
            ],
            'events' => $closedInvestments
                ->map(function (CycleInvestment $investment) use ($investmentPostings): array {
                    $lines = collect($investmentPostings->profitAndLoss($investment));
                    $credit = fn (Account ...$accounts): float => Money::toTaka((int) $lines->only(array_map(fn (Account $a) => $a->value, $accounts))->sum());
                    $sales = $credit(Account::EventSales, Account::EventSalesRefund, Account::BusinessProfit);
                    $otherIncome = $credit(Account::SubBusinessOtherIncome);
                    $totalExpense = -Money::toTaka((int) $lines->filter(fn (int $amount, string $code) => Account::from($code)->type() === 'expense')->sum());

                    return [
                        'id' => $investment->id,
                        'type' => $investment->type,
                        'title' => $investment->title,
                        'total_paid_amount' => $sales,
                        'other_income_amount' => $otherIncome,
                        'total_income_amount' => round($sales + $otherIncome, 2),
                        'total_expense_amount' => round($totalExpense, 2),
                        'net_profit_amount' => Money::toTaka($investmentPostings->result($investment)),
                    ];
                })
                ->values(),
            'cycleResult' => [
                'investments_result' => Money::toTaka($cycleSummary['investments_result']),
                'cycle_income' => Money::toTaka($cycleSummary['cycle_income']),
                'cycle_expense' => Money::toTaka($cycleSummary['cycle_expense']),
                'result' => Money::toTaka($cycleSummary['result']),
                'is_settled' => $cycleSummary['is_settled'],
                'open_investments' => collect($cycleSummary['investments'])->where('status', '!=', CycleInvestment::STATUS_CLOSED)->count(),
                'my_capital' => Money::toTaka((int) $myRows->sum('capital')),
                'my_share' => Money::toTaka((int) $myRows->sum('share')),
                'my_payout' => Money::toTaka((int) $myRows->sum('payout')),
            ],
        ]);
    }
}
