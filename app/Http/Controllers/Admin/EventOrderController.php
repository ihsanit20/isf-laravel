<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventOrder;
use App\Models\FundCycleEvent;
use Inertia\Inertia;
use Inertia\Response;

class EventOrderController extends Controller
{
    public function index(FundCycleEvent $fundCycleEvent): Response
    {
        $orders = EventOrder::query()
            ->where('fund_cycle_event_id', $fundCycleEvent->id)
            ->with(['payments', 'items'])
            ->latest('id')
            ->get()
            ->map(function (EventOrder $order): array {
                $latestPayment = $order->payments->sortByDesc('id')->first();

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'total_amount' => (string) $order->total_amount,
                    'total_quantity' => $order->items->sum('quantity'),
                    'payment_status' => $latestPayment?->payment_status ?? 'unpaid',
                    'created_at' => $order->created_at?->format('d M Y, h:i A'),
                ];
            })
            ->values();

        return Inertia::render('admin/EventOrders', [
            'event' => [
                'id' => $fundCycleEvent->id,
                'title' => $fundCycleEvent->title,
                'slug' => $fundCycleEvent->slug,
            ],
            'orders' => $orders,
        ]);
    }

    public function show(FundCycleEvent $fundCycleEvent, EventOrder $eventOrder): Response
    {
        abort_unless($eventOrder->fund_cycle_event_id === $fundCycleEvent->id, 404);

        $eventOrder->load([
            'items.package:id,name',
            'pickupPoint:id,name,area,address,contact_person,phone',
            'payments' => fn ($query) => $query->latest('id'),
            'statusHistories.changedBy:id,name',
        ]);

        return Inertia::render('admin/EventOrderDetails', [
            'event' => [
                'id' => $fundCycleEvent->id,
                'title' => $fundCycleEvent->title,
                'slug' => $fundCycleEvent->slug,
            ],
            'order' => [
                'id' => $eventOrder->id,
                'order_number' => $eventOrder->order_number,
                'customer_name' => $eventOrder->customer_name,
                'customer_phone' => $eventOrder->customer_phone,
                'customer_address' => $eventOrder->customer_address,
                'status' => $eventOrder->status->value,
                'status_label' => $eventOrder->status->label(),
                'total_amount' => (string) $eventOrder->total_amount,
                'advance_amount' => (string) $eventOrder->advance_amount,
                'created_at' => $eventOrder->created_at?->format('d M Y, h:i A'),
                'confirmed_at' => $eventOrder->confirmed_at?->format('d M Y, h:i A'),
                'pickup_point' => $eventOrder->pickupPoint ? [
                    'name' => $eventOrder->pickupPoint->name,
                    'area' => $eventOrder->pickupPoint->area,
                    'address' => $eventOrder->pickupPoint->address,
                    'contact_person' => $eventOrder->pickupPoint->contact_person,
                    'phone' => $eventOrder->pickupPoint->phone,
                ] : null,
                'items' => $eventOrder->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'package_name' => $item->package?->name ?? '-',
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values(),
                'payments' => $eventOrder->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'amount' => (string) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'transaction_reference' => $payment->transaction_reference,
                    'paid_at' => $payment->paid_at?->format('d M Y, h:i A'),
                    'verified_at' => $payment->verified_at?->format('d M Y, h:i A'),
                ])->values(),
                'status_histories' => $eventOrder->statusHistories
                    ->sortByDesc('changed_at')
                    ->values()
                    ->map(fn ($history): array => [
                        'id' => $history->id,
                        'status' => $history->status,
                        'note' => $history->note,
                        'changed_at' => $history->changed_at?->format('d M Y, h:i A'),
                        'changed_by' => $history->changedBy?->name,
                    ]),
            ],
        ]);
    }
}

