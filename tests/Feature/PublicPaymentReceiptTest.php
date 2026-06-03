<?php

use App\Enums\EventOrderStatus;
use App\Enums\EventPaymentType;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;

use function Pest\Laravel\get;

/**
 * @return array{event: FundCycleEvent, order: EventOrder, payment: EventPayment}
 */
function createTrackableOrderWithPayment(): array
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Receipt Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Receipt Event',
        'slug' => 'receipt-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $pickup = $event->pickupPoints()->create([
        'name' => 'Hub',
        'is_active' => true,
    ]);

    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'event_pickup_point_id' => $pickup->id,
        'order_number' => 'FC1E1-R01',
        'tracking_token' => 'tracktoken1234',
        'customer_name' => 'Receipt Customer',
        'customer_phone' => '01733334444',
        'status' => EventOrderStatus::Confirmed,
        'total_amount' => 1000,
        'advance_amount' => 200,
        'confirmed_at' => now(),
    ]);

    $payment = EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 200,
        'payment_type' => EventPaymentType::Advance,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
        'transaction_reference' => 'TRX123456',
        'paid_at' => now(),
        'verified_at' => now(),
    ]);

    return compact('event', 'order', 'payment');
}

test('verified payment receipt renders html with valid tracking token', function () {
    ['order' => $order, 'payment' => $payment] = createTrackableOrderWithPayment();

    get(route('public.orders.payments.receipt', [
        'orderNumber' => $order->order_number,
        'payment' => $payment->id,
        'token' => 'tracktoken1234',
    ]))
        ->assertOk()
        ->assertSee('পেমেন্ট রিসিপ্ট')
        ->assertSee('PAY-'.$payment->id)
        ->assertSee('FC1E1-R01')
        ->assertSee('Receipt Customer')
        ->assertSee('TRX123456')
        ->assertSee('অগ্রিম');
});

test('verified payment receipt downloads pdf with valid customer phone', function () {
    ['order' => $order, 'payment' => $payment] = createTrackableOrderWithPayment();

    get(route('public.orders.payments.receipt', [
        'orderNumber' => $order->order_number,
        'payment' => $payment->id,
        'customer_phone' => '01733334444',
        'download' => 'pdf',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('payment receipt returns 404 for invalid token', function () {
    ['order' => $order, 'payment' => $payment] = createTrackableOrderWithPayment();

    get(route('public.orders.payments.receipt', [
        'orderNumber' => $order->order_number,
        'payment' => $payment->id,
        'token' => 'wrong-token',
    ]))->assertNotFound();
});

test('payment receipt returns 404 for invalid customer phone', function () {
    ['order' => $order, 'payment' => $payment] = createTrackableOrderWithPayment();

    get(route('public.orders.payments.receipt', [
        'orderNumber' => $order->order_number,
        'payment' => $payment->id,
        'customer_phone' => '01999999999',
    ]))->assertNotFound();
});

test('payment receipt returns 404 for pending payment', function () {
    ['order' => $order] = createTrackableOrderWithPayment();

    $pendingPayment = EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 100,
        'payment_type' => EventPaymentType::Due,
        'payment_method' => 'bkash',
        'payment_status' => 'pending',
    ]);

    get(route('public.orders.payments.receipt', [
        'orderNumber' => $order->order_number,
        'payment' => $pendingPayment->id,
        'token' => 'tracktoken1234',
    ]))->assertNotFound();
});

test('payment receipt returns 404 when payment belongs to another order', function () {
    ['order' => $order, 'payment' => $payment] = createTrackableOrderWithPayment();

    $otherOrder = EventOrder::query()->create([
        'fund_cycle_event_id' => $order->fund_cycle_event_id,
        'event_pickup_point_id' => $order->event_pickup_point_id,
        'order_number' => 'FC1E1-R02',
        'customer_name' => 'Other',
        'customer_phone' => '01755556666',
        'status' => EventOrderStatus::Pending,
        'total_amount' => 500,
        'advance_amount' => 100,
    ]);

    get(route('public.orders.payments.receipt', [
        'orderNumber' => $otherOrder->order_number,
        'payment' => $payment->id,
        'customer_phone' => '01755556666',
    ]))->assertNotFound();
});
