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
 * @return array{order: EventOrder, verifiedPayment: EventPayment, pendingPayment: EventPayment}
 */
function createOrderWithPaymentsForTracking(): array
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Track Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Track Event',
        'slug' => 'track-event-'.uniqid(),
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
        'order_number' => 'FC1E1-T01',
        'tracking_token' => 'tracktok123456',
        'customer_name' => 'Track Customer',
        'customer_phone' => '01788889999',
        'status' => EventOrderStatus::Confirmed,
        'total_amount' => 1000,
        'advance_amount' => 300,
        'confirmed_at' => now(),
    ]);

    $verifiedPayment = EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 300,
        'payment_type' => EventPaymentType::Advance,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
        'transaction_reference' => 'TRX-ADV',
        'paid_at' => now()->subHour(),
        'verified_at' => now()->subHour(),
    ]);

    $pendingPayment = EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 700,
        'payment_type' => EventPaymentType::Due,
        'payment_method' => 'bkash',
        'payment_status' => 'pending',
    ]);

    return compact('order', 'verifiedPayment', 'pendingPayment');
}

test('track by token includes payments with receipt url for verified only', function () {
    ['order' => $order, 'verifiedPayment' => $verifiedPayment, 'pendingPayment' => $pendingPayment] = createOrderWithPaymentsForTracking();

    $response = get('/api/v1/orders/track-by-token?token=tracktok123456');

    $response->assertOk()
        ->assertJsonCount(2, 'data.payments')
        ->assertJsonPath('data.payments.0.id', $pendingPayment->id)
        ->assertJsonPath('data.payments.0.payment_status', 'pending')
        ->assertJsonPath('data.payments.0.receipt_url', null)
        ->assertJsonPath('data.payments.1.id', $verifiedPayment->id)
        ->assertJsonPath('data.payments.1.payment_status', 'verified')
        ->assertJsonPath('data.payments.1.payment_type_label', 'অগ্রিম');

    $receiptUrl = $response->json('data.payments.1.receipt_url');
    expect($receiptUrl)->toContain('/public/orders/'.$order->order_number.'/payments/'.$verifiedPayment->id.'/receipt')
        ->and($receiptUrl)->toContain('token=tracktok123456');
});

test('track by order number and phone includes receipt url with customer phone', function () {
    ['order' => $order, 'verifiedPayment' => $verifiedPayment] = createOrderWithPaymentsForTracking();

    $response = get('/api/v1/orders/track?'.http_build_query([
        'order_number' => $order->order_number,
        'customer_phone' => $order->customer_phone,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.payments.1.id', $verifiedPayment->id);

    $receiptUrl = $response->json('data.payments.1.receipt_url');
    expect($receiptUrl)->toContain('customer_phone=01788889999');
});

test('track response allows due bkash when confirmed order has due balance', function () {
    ['order' => $order] = createOrderWithPaymentsForTracking();

    EventPayment::query()->where('event_order_id', $order->id)
        ->where('payment_status', 'pending')
        ->delete();

    $response = get('/api/v1/orders/track?'.http_build_query([
        'order_number' => $order->order_number,
        'customer_phone' => $order->customer_phone,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.due_amount', 700)
        ->assertJsonPath('data.can_pay_due', true);
});

test('track response allows due bkash for delivered order with due balance', function () {
    ['order' => $order] = createOrderWithPaymentsForTracking();

    EventPayment::query()->where('event_order_id', $order->id)
        ->where('payment_status', 'pending')
        ->delete();

    $order->update(['status' => EventOrderStatus::Delivered]);

    $response = get('/api/v1/orders/track?'.http_build_query([
        'order_number' => $order->order_number,
        'customer_phone' => $order->customer_phone,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.due_amount', 700)
        ->assertJsonPath('data.can_pay_due', true);
});

test('track response blocks due bkash while another payment is pending', function () {
    ['order' => $order] = createOrderWithPaymentsForTracking();

    $response = get('/api/v1/orders/track?'.http_build_query([
        'order_number' => $order->order_number,
        'customer_phone' => $order->customer_phone,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.due_amount', 700)
        ->assertJsonPath('data.can_pay_due', false);
});
