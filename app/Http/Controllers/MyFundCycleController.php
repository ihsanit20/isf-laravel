<?php

namespace App\Http\Controllers;

use App\Enums\FundCycleEventStatus;
use App\Models\EventBankDeposit;
use App\Models\EventBankWithdrawal;
use App\Models\EventExpense;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleEvent;
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

    public function show(Request $request, FundCycle $fundCycle): Response
    {
        /** @var User $user */
        $user = $request->user();

        $fundCycle->loadCount('allocations')->loadSum('allocations', 'amount');

        $myAllocatedAmount = (int) FundCycleAllocation::query()
            ->where('fund_cycle_id', $fundCycle->id)
            ->whereHas('member', fn ($query) => $query->where('managed_by_user_id', $user->id))
            ->sum('amount');

        $events = $fundCycle->events()
            ->where('status', '!=', FundCycleEventStatus::Draft)
            ->where('is_finalized', true)
            ->oldest('order_open_at')
            ->oldest('id')
            ->get();

        $eventIds = $events->pluck('id');

        $paidTotals = EventPayment::query()
            ->join('event_orders', 'event_orders.id', '=', 'event_payments.event_order_id')
            ->where('event_payments.payment_status', 'verified')
            ->whereIn('event_orders.fund_cycle_event_id', $eventIds)
            ->selectRaw('event_orders.fund_cycle_event_id as event_id, SUM(event_payments.amount) as total')
            ->groupBy('event_orders.fund_cycle_event_id')
            ->pluck('total', 'event_id');

        $expenseTotals = EventExpense::query()
            ->whereIn('fund_cycle_event_id', $eventIds)
            ->selectRaw('fund_cycle_event_id, SUM(amount) as total')
            ->groupBy('fund_cycle_event_id')
            ->pluck('total', 'fund_cycle_event_id');

        $bankDepositTotals = EventBankDeposit::query()
            ->whereIn('fund_cycle_event_id', $eventIds)
            ->selectRaw('fund_cycle_event_id, SUM(amount) as total')
            ->groupBy('fund_cycle_event_id')
            ->pluck('total', 'fund_cycle_event_id');

        $bankWithdrawalTotals = EventBankWithdrawal::query()
            ->whereIn('fund_cycle_event_id', $eventIds)
            ->selectRaw('fund_cycle_event_id, SUM(amount) as total')
            ->groupBy('fund_cycle_event_id')
            ->pluck('total', 'fund_cycle_event_id');

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
            'events' => $events
                ->map(function (FundCycleEvent $event) use (
                    $paidTotals,
                    $expenseTotals,
                    $bankDepositTotals,
                    $bankWithdrawalTotals,
                ): array {
                    $totalPaid = round((float) ($paidTotals[$event->id] ?? 0), 2);
                    $totalExpense = (int) ($expenseTotals[$event->id] ?? 0);
                    $bankDeposit = (int) ($bankDepositTotals[$event->id] ?? 0);
                    $bankWithdrawal = (int) ($bankWithdrawalTotals[$event->id] ?? 0);

                    // "Other income" reconciles bank cash flow (deposits minus
                    // withdrawals) against order-tracked profit — money that
                    // reached the bank without being logged as an order payment.
                    $orderProfit = $totalPaid - $totalExpense;
                    $bankProfit = $bankDeposit - $bankWithdrawal;
                    $otherIncome = round($bankProfit - $orderProfit, 2);
                    $totalIncome = round($totalPaid + $otherIncome, 2);

                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'total_paid_amount' => $totalPaid,
                        'other_income_amount' => $otherIncome,
                        'total_income_amount' => $totalIncome,
                        'total_expense_amount' => $totalExpense,
                        'net_profit_amount' => round($totalIncome - $totalExpense, 2),
                    ];
                })
                ->values(),
        ]);
    }
}
