<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\EventOrder;
use App\Models\EventRefund;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventRefundController extends Controller
{
    public function store(
        Request $request,
        FundCycleEvent $fundCycleEvent,
        EventOrder $eventOrder,
        InvestmentPostings $postings,
        Ledger $ledger,
    ): RedirectResponse {
        abort_unless($eventOrder->fund_cycle_event_id === $fundCycleEvent->id, 404);
        $fundCycleEvent->ensureNotFinalized();

        $attributes = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in(EventRefund::METHODS)],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'refunded_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($request, $eventOrder, $attributes, $postings, $ledger): void {
            $ledger->lock('order:'.$eventOrder->id);

            $netPaid = $ledger->creditBalance(
                [Account::EventSales, Account::EventSalesRefund],
                ['event_order_id' => $eventOrder->id],
            );

            if (Money::toPaisa($attributes['amount']) > $netPaid) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('Refund cannot exceed what the customer has paid (%s BDT).', Money::format($netPaid)),
                ]);
            }

            $refund = EventRefund::query()->create([
                ...$attributes,
                'event_order_id' => $eventOrder->id,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $postings->refund($refund, $request->user());
        });

        return back();
    }
}
