<?php

use App\Enums\EventOrderStatus;
use App\Enums\EventPackageStatus;
use App\Enums\EventPaymentType;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Config::set('services.frontend.url', 'http://events.test');
    Config::set('app.url', 'http://api.test');
    Config::set('bkash.accounts.primary', [
        'sandbox' => true,
        'app_key' => 'test-key',
        'app_secret' => 'test-secret',
        'username' => 'test-user',
        'password' => 'test-pass',
    ]);
});

function createPendingEventOrder(float $advance = 500.00): EventOrder
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Test Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Test Event',
        'slug' => 'test-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    return EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'order_number' => 'ISF-'.now()->format('ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'customer_name' => 'Test Customer',
        'customer_phone' => '01700000000',
        'status' => EventOrderStatus::Pending,
        'total_amount' => 1000,
        'advance_amount' => $advance,
    ]);
}

test('bkash init rejects wrong phone', function () {
    $order = createPendingEventOrder();

    $response = postJson("/api/v1/orders/{$order->order_number}/bkash/init", [
        'customer_phone' => '01999999999',
    ]);

    $response->assertNotFound();
});

test('bkash init rejects already confirmed order', function () {
    $order = createPendingEventOrder();
    $order->update([
        'status' => EventOrderStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    $response = postJson("/api/v1/orders/{$order->order_number}/bkash/init", [
        'customer_phone' => $order->customer_phone,
    ]);

    $response->assertUnprocessable();
});

test('bkash init returns redirect url and creates pending payment', function () {
    $order = createPendingEventOrder();

    Http::fake([
        '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'sandbox-token']),
        '*/tokenized/checkout/create' => Http::response([
            'paymentID' => 'TR0001',
            'bkashURL' => 'https://sandbox.bka.sh/pay/TR0001',
        ]),
    ]);

    $response = postJson("/api/v1/orders/{$order->order_number}/bkash/init", [
        'customer_phone' => $order->customer_phone,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.redirect_url', 'https://sandbox.bka.sh/pay/TR0001');

    assertDatabaseHas('event_payments', [
        'event_order_id' => $order->id,
        'bkash_payment_id' => 'TR0001',
        'payment_status' => 'pending',
        'payment_method' => 'bkash',
        'payment_type' => EventPaymentType::Advance->value,
    ]);
});

test('bkash init-due rejects pending orders', function () {
    $order = createPendingEventOrder();

    $response = postJson("/api/v1/orders/{$order->order_number}/bkash/init-due", [
        'customer_phone' => $order->customer_phone,
    ]);

    $response->assertUnprocessable();
});

test('bkash callback confirms order on successful execute', function () {
    $order = createPendingEventOrder();
    $invoice = 'ISF-'.$order->id.'-testinv01';

    EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => $order->advance_amount,
        'payment_method' => 'bkash',
        'payment_status' => 'pending',
        'bkash_payment_id' => 'TR0001',
        'merchant_invoice' => $invoice,
    ]);

    Http::fake([
        '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'sandbox-token']),
        '*/tokenized/checkout/execute' => Http::response([
            'transactionStatus' => 'Completed',
            'merchantInvoiceNumber' => $invoice,
            'amount' => (string) $order->advance_amount,
            'trxID' => '8A7X9YZ',
        ]),
    ]);

    $response = get('/bkash/callback?paymentID=TR0001&status=success');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('status=success')
        ->and($response->headers->get('Location'))->toContain(urlencode($order->order_number));

    $order->refresh();
    expect($order->status)->toBe(EventOrderStatus::Confirmed)
        ->and($order->confirmed_at)->not->toBeNull();

    assertDatabaseHas('event_payments', [
        'event_order_id' => $order->id,
        'payment_status' => 'verified',
        'transaction_reference' => '8A7X9YZ',
    ]);
});

test('bkash callback is idempotent when order already confirmed', function () {
    $order = createPendingEventOrder();
    $invoice = 'ISF-'.$order->id.'-testinv02';

    EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => $order->advance_amount,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
        'bkash_payment_id' => 'TR0002',
        'merchant_invoice' => $invoice,
    ]);

    $order->update([
        'status' => EventOrderStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    Http::fake();

    $response = get('/bkash/callback?paymentID=TR0002&status=success');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('status=success');
    Http::assertNothingSent();
});

test('order with zero advance is confirmed on placement', function () {
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Test Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Zero Advance Event',
        'slug' => 'zero-advance-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $package = $event->packages()->create([
        'name' => 'Basic',
        'package_price' => 100,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    $pickup = $event->pickupPoints()->create([
        'name' => 'Hub 1',
        'is_active' => true,
    ]);

    $response = postJson('/api/v1/orders', [
        'event_slug' => $event->slug,
        'customer_name' => 'Buyer',
        'customer_phone' => '01711111111',
        'pickup_point_id' => $pickup->id,
        'items' => [
            ['package_id' => $package->id, 'quantity' => 1],
        ],
    ]);

    $response->assertCreated();

    $order = EventOrder::query()->where('order_number', $response->json('data.order_number'))->first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(EventOrderStatus::Confirmed)
        ->and($response->json('data.order_number'))->toBe("FC{$cycle->id}E{$event->id}-001");
});

test('order numbers encode fund cycle id event id and per-event sequence', function () {
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Cycle A',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Event A',
        'slug' => 'event-a-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $package = $event->packages()->create([
        'name' => 'Pkg',
        'package_price' => 200,
        'advance_percent' => 50,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    $pickup = $event->pickupPoints()->create([
        'name' => 'Hub',
        'is_active' => true,
    ]);

    $payload = [
        'event_slug' => $event->slug,
        'customer_name' => 'Buyer',
        'customer_phone' => '01722222222',
        'pickup_point_id' => $pickup->id,
        'items' => [
            ['package_id' => $package->id, 'quantity' => 1],
        ],
    ];

    Http::fake([
        '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 't']),
        '*/tokenized/checkout/create' => Http::response([
            'paymentID' => 'PAY1',
            'bkashURL' => 'https://sandbox.bka.sh/pay',
        ]),
    ]);

    $first = postJson('/api/v1/orders', $payload);
    $first->assertCreated();
    expect($first->json('data.order_number'))->toBe("FC{$cycle->id}E{$event->id}-001");

    $second = postJson('/api/v1/orders', array_merge($payload, [
        'customer_phone' => '01733333333',
    ]));
    $second->assertCreated();
    expect($second->json('data.order_number'))->toBe("FC{$cycle->id}E{$event->id}-002");
});
