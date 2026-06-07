<?php

use App\Enums\EventOrderStatus;
use App\Enums\EventPaymentType;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/**
 * @return array{event: FundCycleEvent, order: EventOrder, admin: User}
 */
function createConfirmedOrderWithDue(float $total = 1000, float $verifiedPaid = 200): array
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Due Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Due Event',
        'slug' => 'due-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'order_number' => 'FC1E1-DUE',
        'customer_name' => 'Due Buyer',
        'customer_phone' => '01744444444',
        'status' => EventOrderStatus::Confirmed,
        'total_amount' => $total,
        'advance_amount' => $verifiedPaid,
        'confirmed_at' => now(),
    ]);

    if ($verifiedPaid > 0) {
        EventPayment::query()->create([
            'event_order_id' => $order->id,
            'amount' => $verifiedPaid,
            'payment_type' => EventPaymentType::Advance,
            'payment_method' => 'bkash',
            'payment_status' => 'verified',
            'paid_at' => now(),
            'verified_at' => now(),
        ]);
    }

    $admin = User::factory()->create(['role' => 'admin']);

    return ['event' => $event, 'order' => $order, 'admin' => $admin];
}

test('due amount is total minus verified payments', function () {
    ['order' => $order] = createConfirmedOrderWithDue(1000, 200);

    expect($order->dueAmount())->toBe(800.0)
        ->and($order->totalVerifiedPaid())->toBe(200.0);
});

test('admin manual payment verify reduces due balance', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createConfirmedOrderWithDue();

    $showUrl = route('admin.events.orders.show', [$event, $order]);

    actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.events.orders.payments.store', [$event, $order]), [
            'amount' => 300,
            'payment_method' => 'cash',
            'note' => 'Hub collection',
        ])
        ->assertRedirect($showUrl);

    $payment = EventPayment::query()
        ->where('event_order_id', $order->id)
        ->where('payment_status', 'pending')
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->payment_type)->toBe(EventPaymentType::Manual);

    actingAs($admin)
        ->from($showUrl)
        ->patch(route('admin.events.orders.payments.review', [$event, $order, $payment]), [
            'status' => 'verified',
        ])
        ->assertRedirect($showUrl);

    $order->refresh();

    expect($order->dueAmount())->toBe(500.0)
        ->and($order->totalVerifiedPaid())->toBe(500.0);
});

test('delivered status is blocked when due remains', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createConfirmedOrderWithDue();

    actingAs($admin)
        ->patch(route('admin.events.orders.status.update', [$event, $order]), [
            'status' => 'delivered',
            'note' => 'Handed over',
        ])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(EventOrderStatus::Confirmed);
});

test('delivered status can override when due remains', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createConfirmedOrderWithDue();

    $showUrl = route('admin.events.orders.show', [$event, $order]);

    actingAs($admin)
        ->from($showUrl)
        ->patch(route('admin.events.orders.status.update', [$event, $order]), [
            'status' => 'delivered',
            'note' => 'Delivered with agreement',
            'allow_delivered_with_due' => true,
        ])
        ->assertRedirect($showUrl);

    expect($order->refresh()->status)->toBe(EventOrderStatus::Delivered);
});

test('delivered order with due can record and verify manual payment', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createConfirmedOrderWithDue();
    $showUrl = route('admin.events.orders.show', [$event, $order]);

    actingAs($admin)
        ->from($showUrl)
        ->patch(route('admin.events.orders.status.update', [$event, $order]), [
            'status' => 'delivered',
            'note' => 'Delivered with agreement',
            'allow_delivered_with_due' => true,
        ])
        ->assertRedirect($showUrl);

    actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.events.orders.payments.store', [$event, $order]), [
            'amount' => 300,
            'payment_method' => 'cash',
            'note' => 'Collected after delivery',
        ])
        ->assertRedirect($showUrl);

    $payment = EventPayment::query()
        ->where('event_order_id', $order->id)
        ->where('payment_status', 'pending')
        ->latest('id')
        ->first();

    expect($payment)->not->toBeNull();

    actingAs($admin)
        ->from($showUrl)
        ->patch(route('admin.events.orders.payments.review', [$event, $order, $payment]), [
            'status' => 'verified',
        ])
        ->assertRedirect($showUrl);

    $order->refresh();

    expect($order->status)->toBe(EventOrderStatus::Delivered)
        ->and($order->dueAmount())->toBe(500.0);
});

test('delivered order with due can initiate bkash due payment', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createConfirmedOrderWithDue();

    actingAs($admin)
        ->patch(route('admin.events.orders.status.update', [$event, $order]), [
            'status' => 'delivered',
            'note' => 'Delivered with agreement',
            'allow_delivered_with_due' => true,
        ])
        ->assertRedirect();

    Http::fake([
        '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'sandbox-token']),
        '*/tokenized/checkout/create' => Http::response([
            'paymentID' => 'TRDUE03',
            'bkashURL' => 'https://sandbox.bka.sh/pay/TRDUE03',
        ]),
    ]);

    $response = postJson("/api/v1/orders/{$order->order_number}/bkash/init-due", [
        'customer_phone' => $order->customer_phone,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.redirect_url', 'https://sandbox.bka.sh/pay/TRDUE03');

    assertDatabaseHas('event_payments', [
        'event_order_id' => $order->id,
        'bkash_payment_id' => 'TRDUE03',
        'payment_status' => 'pending',
        'payment_type' => EventPaymentType::Due->value,
        'amount' => 800,
    ]);
});

test('full manual verify clears due and allows delivered', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createConfirmedOrderWithDue();

    $showUrl = route('admin.events.orders.show', [$event, $order]);

    actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.events.orders.payments.store', [$event, $order]), [
            'amount' => 800,
            'payment_method' => 'cash',
        ])
        ->assertRedirect($showUrl);

    $payment = EventPayment::query()
        ->where('event_order_id', $order->id)
        ->where('payment_status', 'pending')
        ->latest('id')
        ->first();

    actingAs($admin)
        ->from($showUrl)
        ->patch(route('admin.events.orders.payments.review', [$event, $order, $payment]), [
            'status' => 'verified',
        ])
        ->assertRedirect($showUrl);

    $order->refresh();
    expect($order->dueAmount())->toBe(0.0);

    actingAs($admin)
        ->from($showUrl)
        ->patch(route('admin.events.orders.status.update', [$event, $order]), [
            'status' => 'delivered',
            'note' => 'All paid',
        ])
        ->assertRedirect($showUrl);

    expect($order->refresh()->status)->toBe(EventOrderStatus::Delivered);
});

test('bkash due init creates pending due payment for confirmed order', function () {
    ['order' => $order] = createConfirmedOrderWithDue();

    Http::fake([
        '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'sandbox-token']),
        '*/tokenized/checkout/create' => Http::response([
            'paymentID' => 'TRDUE01',
            'bkashURL' => 'https://sandbox.bka.sh/pay/TRDUE01',
        ]),
    ]);

    $response = postJson("/api/v1/orders/{$order->order_number}/bkash/init-due", [
        'customer_phone' => $order->customer_phone,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.redirect_url', 'https://sandbox.bka.sh/pay/TRDUE01');

    assertDatabaseHas('event_payments', [
        'event_order_id' => $order->id,
        'bkash_payment_id' => 'TRDUE01',
        'payment_status' => 'pending',
        'payment_type' => EventPaymentType::Due->value,
        'amount' => 800,
    ]);
});

test('bkash due callback verifies payment without reconfirming order', function () {
    ['order' => $order] = createConfirmedOrderWithDue();
    $invoice = 'ISF-'.$order->id.'-due-test01';

    EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 800,
        'payment_type' => EventPaymentType::Due,
        'payment_method' => 'bkash',
        'payment_status' => 'pending',
        'bkash_payment_id' => 'TRDUE02',
        'merchant_invoice' => $invoice,
    ]);

    Http::fake([
        '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'sandbox-token']),
        '*/tokenized/checkout/execute' => Http::response([
            'transactionStatus' => 'Completed',
            'merchantInvoiceNumber' => $invoice,
            'amount' => '800',
            'trxID' => 'DUE123',
        ]),
    ]);

    $response = get('/bkash/callback?paymentID=TRDUE02&status=success');

    $response->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(EventOrderStatus::Confirmed)
        ->and($order->dueAmount())->toBe(0.0);

    assertDatabaseHas('event_payments', [
        'event_order_id' => $order->id,
        'payment_type' => EventPaymentType::Due->value,
        'payment_status' => 'verified',
        'transaction_reference' => 'DUE123',
    ]);
});

test('event orders summary confirmed due matches verified payment math', function () {
    ['event' => $event, 'admin' => $admin] = createConfirmedOrderWithDue(1500, 500);

    EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'order_number' => 'FC1E1-ZERO',
        'customer_name' => 'Fully Paid',
        'customer_phone' => '01755555555',
        'status' => EventOrderStatus::Confirmed,
        'total_amount' => 600,
        'advance_amount' => 600,
        'confirmed_at' => now(),
    ]);

    EventPayment::query()->create([
        'event_order_id' => EventOrder::query()->where('order_number', 'FC1E1-ZERO')->value('id'),
        'amount' => 600,
        'payment_type' => EventPaymentType::Advance,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
        'paid_at' => now(),
        'verified_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.events.orders.index', $event))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('summary.focus.confirmed_due_amount', '1000.00')
                ->where('summary.focus.confirmed_orders_with_due_count', 1),
        );
});
