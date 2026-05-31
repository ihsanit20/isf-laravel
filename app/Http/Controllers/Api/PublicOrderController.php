<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceOrderRequest;
use App\Models\EventOrder;
use App\Models\EventOrderItem;
use App\Models\EventOrderStatusHistory;
use App\Models\EventPackage;
use App\Models\FundCycleEvent;
use App\Services\EventBkashPaymentService;
use App\Support\EventOrderNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicOrderController extends Controller
{
    public function __construct(
        private readonly EventBkashPaymentService $bkashPayments,
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $event = FundCycleEvent::query()
            ->where('slug', $request->event_slug)
            ->where('status', 'published')
            ->firstOrFail();

        // Validate order window
        $now = now();
        if ($event->order_open_at && $now->lt($event->order_open_at)) {
            return response()->json(['message' => 'Order window has not opened yet.'], 422);
        }
        if ($event->order_close_at && $now->gt($event->order_close_at)) {
            return response()->json(['message' => 'Order window is closed.'], 422);
        }

        // Load & validate packages belong to this event
        $packageIds = collect($request->items)->pluck('package_id')->unique()->all();
        $packages = EventPackage::query()
            ->whereIn('id', $packageIds)
            ->where('fund_cycle_event_id', $event->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        if ($packages->count() !== count($packageIds)) {
            return response()->json(['message' => 'One or more packages are not available for this event.'], 422);
        }

        // Validate quantities and build line items
        $lineItems = [];
        $totalAmount = 0;
        $totalAdvance = 0;
        $advancePercents = [];

        foreach ($request->items as $item) {
            $pkg = $packages[$item['package_id']];
            $qty = (int) $item['quantity'];

            if ($qty < $pkg->min_qty_per_order) {
                return response()->json([
                    'message' => "Minimum quantity for \"{$pkg->name}\" is {$pkg->min_qty_per_order}.",
                ], 422);
            }

            if ($pkg->max_qty_per_order !== null && $qty > $pkg->max_qty_per_order) {
                return response()->json([
                    'message' => "Maximum quantity for \"{$pkg->name}\" is {$pkg->max_qty_per_order}.",
                ], 422);
            }

            $remaining = $pkg->remainingQty();
            if ($remaining !== null && $qty > $remaining) {
                return response()->json([
                    'message' => "Only {$remaining} unit(s) left for \"{$pkg->name}\".",
                ], 422);
            }

            $unitPrice = (float) $pkg->unit_price;
            $lineTotal = round($unitPrice * $qty, 2);
            $totalAmount += $lineTotal;
            $advancePercents[] = (float) $pkg->advance_percent;

            $lineItems[] = [
                'event_package_id' => $pkg->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        // Advance amount: use the highest advance percent across selected packages
        $maxAdvancePercent = count($advancePercents) ? max($advancePercents) : 0;
        $totalAdvance = round($totalAmount * $maxAdvancePercent / 100, 2);

        $order = DB::transaction(function () use ($event, $request, $lineItems, $totalAmount, $totalAdvance) {
            $order = EventOrder::create([
                'fund_cycle_event_id' => $event->id,
                'event_pickup_point_id' => $request->pickup_point_id,
                'order_number' => EventOrderNumber::generateForEvent($event),
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'status' => EventOrderStatus::Pending->value,
                'total_amount' => $totalAmount,
                'advance_amount' => $totalAdvance,
            ]);

            foreach ($lineItems as $item) {
                EventOrderItem::create(array_merge($item, ['event_order_id' => $order->id]));
            }

            // Increment sold_qty for each package
            foreach ($lineItems as $item) {
                EventPackage::where('id', $item['event_package_id'])
                    ->increment('sold_qty', $item['quantity']);
            }

            // Record initial status history
            EventOrderStatusHistory::create([
                'event_order_id' => $order->id,
                'status' => EventOrderStatus::Pending->value,
                'note' => $totalAdvance > 0
                    ? 'Order placed. Awaiting bKash advance payment.'
                    : 'Order placed by customer.',
                'changed_by_user_id' => null,
                'changed_at' => now(),
            ]);

            return $order;
        });

        if ((float) $order->advance_amount <= 0) {
            $this->bkashPayments->confirmOrderWithoutPayment($order);
            $order->refresh();
        }

        $requiresPayment = (float) $order->advance_amount > 0;

        return response()->json([
            'message' => $requiresPayment
                ? 'Order placed. Advance payment via bKash is required to confirm.'
                : 'Order placed successfully.',
            'data' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'total_amount' => (float) $order->total_amount,
                'advance_amount' => (float) $order->advance_amount,
                'requires_payment' => $requiresPayment,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
            ],
        ], 201);
    }
}
