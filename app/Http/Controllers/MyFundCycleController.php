<?php

namespace App\Http\Controllers;

use App\Ledger\Account;
use App\Ledger\Money;
use App\Ledger\Postings\CyclePostings;
use App\Ledger\Postings\InvestmentPostings;
use App\Ledger\Postings\MemberPostings;
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

        $memberPostings = app(MemberPostings::class);
        $allocatedTotals = $memberPostings->allocatedCapitalBy('fund_cycle_id');
        $myAllocatedTotals = $memberPostings->allocatedCapitalBy('fund_cycle_id', ['user_id' => $user->id]);

        return Inertia::render('FundCycles', [
            'fundCycles' => FundCycle::query()
                ->where('status', '!=', FundCycle::STATUS_DRAFT)
                ->withCount('allocations')
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
                    'total_allocated_amount' => Money::toTaka($allocatedTotals->get($fundCycle->id, 0)),
                    'my_allocated_amount' => Money::toTaka($myAllocatedTotals->get($fundCycle->id, 0)),
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

        $fundCycle->loadCount('allocations');

        $memberPostings = app(MemberPostings::class);
        $totalAllocatedAmount = (int) $memberPostings->allocatedCapitalBy('fund_cycle_id', ['fund_cycle_id' => $fundCycle->id])->sum();
        $myAllocatedAmount = (int) $memberPostings->allocatedCapitalBy('fund_cycle_id', ['fund_cycle_id' => $fundCycle->id, 'user_id' => $user->id])->sum();

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
                'notes' => $fundCycle->notes,
                'allocations_count' => (int) $fundCycle->allocations_count,
                'total_allocated_amount' => Money::toTaka($totalAllocatedAmount),
                'my_allocated_amount' => Money::toTaka($myAllocatedAmount),
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
                'open_investments' => collect($cycleSummary['investments'])->where('status', CycleInvestment::STATUS_ACTIVE)->count(),
                'my_capital' => Money::toTaka((int) $myRows->sum('capital')),
                'my_share' => Money::toTaka((int) $myRows->sum('share')),
                'my_payout' => Money::toTaka((int) $myRows->sum('payout')),
            ],
            'myMembers' => $myRows
                ->map(fn (array $row): array => [
                    'member_id' => $row['member_id'],
                    'name' => $row['name'],
                    'capital' => Money::toTaka($row['capital']),
                    'share' => Money::toTaka($row['share']),
                    'payout' => Money::toTaka($row['payout']),
                ])
                ->values(),
            'myAllocations' => FundCycleAllocation::query()
                ->with('member:id,full_name')
                ->where('fund_cycle_id', $fundCycle->id)
                ->whereHas('member', fn ($query) => $query->where('managed_by_user_id', $user->id))
                ->orderBy('allocated_at')
                ->get()
                ->map(fn (FundCycleAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'member_name' => $allocation->member?->full_name,
                    'slot_key' => $allocation->slot_key,
                    'amount' => $allocation->amount,
                    'allocated_at' => $allocation->allocated_at?->format('d M Y'),
                ])
                ->values(),
        ]);
    }
}
