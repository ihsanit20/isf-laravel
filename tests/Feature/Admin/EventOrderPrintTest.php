<?php

use App\Enums\EventOrderStatus;
use App\Enums\EventPackageStatus;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\EventOrderItem;
use App\Models\EventPayment;
use App\Models\EventPickupPoint;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * @return array{event: FundCycleEvent, pickupA: EventPickupPoint, pickupB: EventPickupPoint, orders: array<string, EventOrder>}
 */
function createPrintableEventOrders(): array
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Print Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Print Event',
        'slug' => 'print-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
        'expected_delivery_date' => now()->addWeeks(2)->toDateString(),
    ]);

    $pickupA = $event->pickupPoints()->create([
        'name' => 'Hub Alpha',
        'is_active' => true,
    ]);

    $pickupB = $event->pickupPoints()->create([
        'name' => 'Hub Beta',
        'is_active' => true,
    ]);

    $pending = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'event_pickup_point_id' => $pickupA->id,
        'order_number' => 'FC1E1-P01',
        'customer_name' => 'Pending Customer',
        'customer_phone' => '01711111111',
        'status' => EventOrderStatus::Pending,
        'total_amount' => 1000,
        'advance_amount' => 200,
    ]);

    $confirmed = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'event_pickup_point_id' => $pickupB->id,
        'order_number' => 'FC1E1-C01',
        'customer_name' => 'Confirmed Customer',
        'customer_phone' => '01722222222',
        'status' => EventOrderStatus::Confirmed,
        'total_amount' => 500,
        'advance_amount' => 500,
        'confirmed_at' => now(),
    ]);

    EventPayment::query()->create([
        'event_order_id' => $confirmed->id,
        'amount' => 500,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
        'paid_at' => now(),
        'verified_at' => now(),
    ]);

    return [
        'event' => $event,
        'pickupA' => $pickupA,
        'pickupB' => $pickupB,
        'orders' => [
            'pending' => $pending,
            'confirmed' => $confirmed,
        ],
    ];
}

beforeEach(function () {
    Config::set('services.frontend.url', 'http://events.test');
});

test('guests cannot access event order print routes', function () {
    ['event' => $event, 'pickupB' => $pickupB, 'orders' => $orders] = createPrintableEventOrders();

    get(route('admin.events.orders.print.pickup-all', $event))->assertRedirect(route('login'));
    get(route('admin.events.orders.print.pickup-hub', [$event, $pickupB]))->assertRedirect(route('login'));
    get(route('admin.events.orders.print.package-summary', $event))->assertRedirect(route('login'));
    get(route('admin.events.orders.print.receipt', [$event, $orders['confirmed']]))->assertRedirect(route('login'));
});

test('members cannot access event order print routes', function () {
    ['event' => $event, 'pickupB' => $pickupB, 'orders' => $orders] = createPrintableEventOrders();

    $member = User::factory()->create(['role' => 'member']);

    actingAs($member)
        ->get(route('admin.events.orders.print.pickup-all', $event))
        ->assertForbidden();
    actingAs($member)
        ->get(route('admin.events.orders.print.pickup-hub', [$event, $pickupB]))
        ->assertForbidden();
    actingAs($member)
        ->get(route('admin.events.orders.print.receipt', [$event, $orders['confirmed']]))
        ->assertForbidden();
});

test('pickup hub print includes only confirmed orders by default', function () {
    ['event' => $event, 'pickupA' => $pickupA, 'pickupB' => $pickupB] = createPrintableEventOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.print.pickup-hub', [$event, $pickupA]))
        ->assertOk()
        ->assertSee('Hub Alpha')
        ->assertSee('No orders at this hub')
        ->assertDontSee('FC1E1-P01');

    actingAs($admin)
        ->get(route('admin.events.orders.print.pickup-hub', [$event, $pickupB]))
        ->assertOk()
        ->assertSee('FC1E1-C01')
        ->assertSee('Confirmed Customer')
        ->assertDontSee('FC1E1-P01');
});

test('pickup hub print can include pending orders with status query', function () {
    ['event' => $event, 'pickupA' => $pickupA] = createPrintableEventOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.print.pickup-hub', [
            'fundCycleEvent' => $event,
            'eventPickupPoint' => $pickupA,
            'status' => 'pending',
        ]))
        ->assertOk()
        ->assertSee('FC1E1-P01')
        ->assertSee('Pending Customer');
});

test('customer receipt print shows order details and tracking url', function () {
    ['event' => $event, 'orders' => $orders] = createPrintableEventOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.print.receipt', [$event, $orders['confirmed']]))
        ->assertOk()
        ->assertSee('Order Receipt')
        ->assertSee('FC1E1-C01')
        ->assertSee('Confirmed Customer')
        ->assertSee('01722222222')
        ->assertSee('http://events.test/t/');
});

test('package summary print shows confirmed packing totals', function () {
    ['event' => $event, 'orders' => $orders] = createPrintableEventOrders();

    $pkg = $event->packages()->create([
        'name' => 'Rice 5kg',
        'package_price' => 500,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['confirmed']->id,
        'event_package_id' => $pkg->id,
        'quantity' => 2,
        'package_price' => 500,
        'line_total' => 1000,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['pending']->id,
        'event_package_id' => $pkg->id,
        'quantity' => 5,
        'package_price' => 500,
        'line_total' => 2500,
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.print.package-summary', $event))
        ->assertOk()
        ->assertSee('Package Packing Summary')
        ->assertSee('Rice 5kg')
        ->assertSee('2');
});

test('print routes can download pdf', function () {
    ['event' => $event, 'orders' => $orders] = createPrintableEventOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.print.receipt', [
            'fundCycleEvent' => $event,
            'eventOrder' => $orders['confirmed'],
            'download' => 'pdf',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
