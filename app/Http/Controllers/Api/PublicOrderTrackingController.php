<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicOrderTrackingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => ['required', 'string'],
            'customer_phone' => ['required', 'string'],
        ]);

        $order = EventOrder::query()
            ->where('order_number', $request->order_number)
            ->where('customer_phone', $request->customer_phone)
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'No order found with the given order number and phone.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    public function showByToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:16'],
        ]);

        $order = EventOrder::query()
            ->where('tracking_token', $request->token)
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'No order found for this tracking link.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    private function formatOrder(EventOrder $order): array
    {
        $order->load([
            'items.package:id,name,unit_price',
            'pickupPoint:id,name,area,address,contact_person,phone',
            'statusHistories' => fn ($q) => $q->orderBy('changed_at'),
            'payments:id,event_order_id,payment_status,payment_method,amount',
            'fundCycleEvent:id,title,expected_delivery_date',
        ]);

        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total_amount' => (float) $order->total_amount,
            'advance_amount' => (float) $order->advance_amount,
            'due_amount' => $order->dueAmount(),
            'payment_status' => $order->advancePaymentStatus(),
            'advance_paid' => $order->isAdvancePaid(),
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'event' => [
                'title' => $order->fundCycleEvent->title,
                'expected_delivery_date' => $order->fundCycleEvent->expected_delivery_date?->toDateString(),
            ],
            'pickup_point' => $order->pickupPoint ? [
                'name' => $order->pickupPoint->name,
                'area' => $order->pickupPoint->area,
                'address' => $order->pickupPoint->address,
                'contact_person' => $order->pickupPoint->contact_person,
                'phone' => $order->pickupPoint->phone,
            ] : null,
            'items' => $order->items->map(fn ($item) => [
                'package_name' => $item->package->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]),
            'status_history' => $order->statusHistories->map(fn ($h) => [
                'status' => $h->status,
                'note' => $h->note,
                'changed_at' => $h->changed_at?->toIso8601String(),
            ]),
        ];
    }
}
