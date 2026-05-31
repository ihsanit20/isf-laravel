<?php

use App\Enums\EventOrderStatus;
use App\Enums\EventPackageStatus;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\EventOrderConfirmationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Config::set('services.sms.url', 'http://bulksmsbd.net/api/smsapi');
    Config::set('services.sms.api_key', 'test-api-key');
    Config::set('services.sms.sender_id', '8809617621674');
    Config::set('services.frontend.url', 'http://events.test');
});

function createPublishedEventWithPackage(): array
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

    $package = $event->packages()->create([
        'name' => 'Pkg',
        'unit_price' => 1000,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    $pickup = $event->pickupPoints()->create([
        'name' => 'Hub',
        'is_active' => true,
    ]);

    return compact('event', 'package', 'pickup');
}

test('zero advance order sends confirmation sms with amounts and short track link', function () {
    Http::fake([
        'http://bulksmsbd.net/api/smsapi' => Http::response(['response_code' => '202']),
    ]);

    ['event' => $event, 'package' => $package, 'pickup' => $pickup] = createPublishedEventWithPackage();

    $response = postJson('/api/v1/orders', [
        'event_slug' => $event->slug,
        'customer_name' => 'Buyer',
        'customer_phone' => '01712345678',
        'pickup_point_id' => $pickup->id,
        'items' => [
            ['package_id' => $package->id, 'quantity' => 1],
        ],
    ]);

    $response->assertCreated();

    $order = EventOrder::query()->where('order_number', $response->json('data.order_number'))->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(EventOrderStatus::Confirmed)
        ->and($order->tracking_token)->not->toBeNull();

    expect(SmsLog::query()->count())->toBe(1);

    $smsLog = SmsLog::query()->first();
    expect($smsLog?->status)->toBe(SmsLog::STATUS_SENT)
        ->and($smsLog?->smsable_type)->toBe($order->getMorphClass())
        ->and($smsLog?->smsable_id)->toBe($order->id)
        ->and($smsLog?->message)->toContain('Ihsan Shop')
        ->and($smsLog?->message)->not->toContain('ISF:')
        ->and($smsLog?->message)->toContain($order->order_number)
        ->and($smsLog?->message)->toContain('Total BDT 1000.00')
        ->and($smsLog?->message)->toContain('Advance BDT 0.00')
        ->and($smsLog?->message)->toContain('Due BDT 1000.00')
        ->and($smsLog?->message)->toContain('/t/'.$order->tracking_token);
});

test('track by token returns order without phone in request', function () {
    ['event' => $event, 'package' => $package, 'pickup' => $pickup] = createPublishedEventWithPackage();

    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'event_pickup_point_id' => $pickup->id,
        'order_number' => 'FC1E1EO-099',
        'tracking_token' => 'abc123token',
        'customer_name' => 'Buyer',
        'customer_phone' => '01798765432',
        'status' => EventOrderStatus::Confirmed,
        'total_amount' => 500,
        'advance_amount' => 100,
        'confirmed_at' => now(),
    ]);

    $response = get('/api/v1/orders/track-by-token?token=abc123token');

    $response->assertOk()
        ->assertJsonPath('data.order_number', 'FC1E1EO-099')
        ->assertJsonPath('data.due_amount', 400);
});

test('confirming order twice does not send duplicate sms', function () {
    Http::fake([
        'http://bulksmsbd.net/api/smsapi' => Http::response(['response_code' => '202']),
    ]);

    ['event' => $event, 'package' => $package, 'pickup' => $pickup] = createPublishedEventWithPackage();

    postJson('/api/v1/orders', [
        'event_slug' => $event->slug,
        'customer_name' => 'Buyer',
        'customer_phone' => '01711112222',
        'pickup_point_id' => $pickup->id,
        'items' => [['package_id' => $package->id, 'quantity' => 1]],
    ])->assertCreated();

    expect(SmsLog::query()->count())->toBe(1);

    $order = EventOrder::query()->where('customer_phone', '01711112222')->first();
    app(EventOrderConfirmationService::class)->confirm($order, 'Retry confirm.');

    expect(SmsLog::query()->count())->toBe(1);
});
