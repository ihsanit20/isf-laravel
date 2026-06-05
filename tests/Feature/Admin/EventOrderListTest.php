<?php

use App\Enums\EventOrderStatus;
use App\Enums\EventPackageStatus;
use App\Enums\EventPackageUnitType;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\EventOrderItem;
use App\Models\EventPayment;
use App\Models\EventPickupPoint;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * @return array{event: FundCycleEvent, pickupA: EventPickupPoint, pickupB: EventPickupPoint, orders: array<string, EventOrder>}
 */
function createEventWithFilterableOrders(): array
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Filter Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->subMonth()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Filter Event',
        'slug' => 'filter-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $pickupA = $event->pickupPoints()->create([
        'name' => 'Hub A',
        'contact_person' => 'Karim Ahmed',
        'is_active' => true,
    ]);

    $pickupB = $event->pickupPoints()->create([
        'name' => 'Hub B',
        'is_active' => true,
    ]);

    $pendingUnpaid = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'event_pickup_point_id' => $pickupA->id,
        'order_number' => 'FC1E1-001',
        'customer_name' => 'Pending Buyer',
        'customer_phone' => '01711111111',
        'status' => EventOrderStatus::Pending,
        'total_amount' => 1000,
        'advance_amount' => 200,
        'created_at' => now()->subDays(3),
    ]);

    $confirmedVerified = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'event_pickup_point_id' => $pickupB->id,
        'order_number' => 'FC1E1-002',
        'customer_name' => 'Paid Buyer',
        'customer_phone' => '01722222222',
        'status' => EventOrderStatus::Confirmed,
        'total_amount' => 500,
        'advance_amount' => 500,
        'created_at' => now()->subDay(),
    ]);

    EventPayment::query()->create([
        'event_order_id' => $confirmedVerified->id,
        'amount' => 500,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
    ]);

    $pendingPayment = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'order_number' => 'FC1E1-003',
        'customer_name' => 'Awaiting Payment',
        'customer_phone' => '01733333333',
        'status' => EventOrderStatus::Pending,
        'total_amount' => 800,
        'advance_amount' => 100,
        'created_at' => now(),
    ]);

    EventPayment::query()->create([
        'event_order_id' => $pendingPayment->id,
        'amount' => 100,
        'payment_method' => 'bkash',
        'payment_status' => 'pending',
    ]);

    return [
        'event' => $event,
        'pickupA' => $pickupA,
        'pickupB' => $pickupB,
        'orders' => [
            'pending_unpaid' => $pendingUnpaid,
            'confirmed_verified' => $confirmedVerified,
            'pending_payment' => $pendingPayment,
        ],
    ];
}

test('guests are redirected from event orders list', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    get(route('admin.events.orders.index', $event))
        ->assertRedirect(route('login'));
});

test('admins can visit event orders list with filters and pagination props', function (string $role) {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => $role]);

    actingAs($admin)
        ->get(route('admin.events.orders.index', $event))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/EventOrders')
                ->has('orders.data', 3)
                ->where('orders.total', 3)
                ->has('summary')
                ->where('summary.orders.total', 3)
                ->where('summary.orders.by_status.pending', 2)
                ->where('summary.orders.by_status.confirmed', 1)
                ->where('summary.orders.by_status.cancelled', 0)
                ->where('summary.orders.by_status.delivered', 0)
                ->where('summary.money.total_order_amount', '2300.00')
                ->where('summary.money.total_advance_amount', '800.00')
                ->where('summary.money.total_due_amount', '1800.00')
                ->where('summary.money.orders_with_due_count', 2)
                ->where('summary.payments.unpaid', 1)
                ->where('summary.payments.pending', 1)
                ->where('summary.payments.verified', 1)
                ->where('summary.payments.failed', 0)
                ->where('summary.payments.verified_amount', '500.00')
                ->where('summary.focus.verified_amount', '500.00')
                ->where('summary.focus.confirmed_order_count', 1)
                ->where('summary.focus.confirmed_order_amount', '500.00')
                ->where('summary.focus.confirmed_due_amount', '0.00')
                ->where('summary.focus.confirmed_orders_with_due_count', 0)
                ->where('summary.focus.pending_order_count', 2)
                ->where('summary.focus.verified_payment_count', 1)
                ->where('summary.focus.confirmed_verified_payment_count', 1)
                ->has('summary.pickup_points', 2)
                ->where('summary.pickup_points.0.name', 'Hub A')
                ->where('summary.pickup_points.0.order_count', 0)
                ->where('summary.pickup_points.0.by_status.pending', 1)
                ->where('summary.pickup_points.0.by_status.confirmed', 0)
                ->where('summary.pickup_points.0.total_due_amount', '0.00')
                ->where('summary.pickup_points.1.name', 'Hub B')
                ->where('summary.pickup_points.1.order_count', 1)
                ->where('summary.pickup_points.1.by_status.confirmed', 1)
                ->where('summary.pickup_points.1.by_status.pending', 0)
                ->where('summary.pickup_points.1.total_due_amount', '0.00')
                ->has('filters')
                ->has('filterOptions.statuses', 4)
                ->has('filterOptions.payment_statuses', 4)
                ->has('filterOptions.pickup_points', 2)
                ->where('event.order_open_at', fn ($value) => $value !== null && $value !== '')
                ->where('event.order_close_at', fn ($value) => $value !== null && $value !== ''),
        );
})->with(['admin', 'super_admin']);

test('members cannot visit event orders list', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    $member = User::factory()->create(['role' => 'member']);

    actingAs($member)
        ->get(route('admin.events.orders.index', $event))
        ->assertForbidden();
});

test('event orders list includes pickup point name and contact person', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'search' => 'FC1E1-001',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.pickup_point.name', 'Hub A')
                ->where('orders.data.0.pickup_point.contact_person', 'Karim Ahmed'),
        );
});

test('event details page includes pickup and package order summaries', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/EventDetails')
                ->has('orderSummary.pickup_points', 2),
        );

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/EventDetails')
                ->has('orderSummary.packages'),
        );
});

test('event orders summary stays event-wide when list is filtered', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'status' => 'confirmed',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('summary.orders.total', 3)
                ->where('summary.orders.by_status.confirmed', 1),
        );
});

test('event orders list can filter by status', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'status' => 'confirmed',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'FC1E1-002')
                ->where('filters.status', 'confirmed'),
        );
});

test('event orders list can search by order number name or phone', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'search' => '01733333333',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'FC1E1-003'),
        );
});

test('event orders list can filter by payment status', function (string $paymentStatus, string $expectedOrderNumber) {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'payment_status' => $paymentStatus,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $expectedOrderNumber),
        );
})->with([
    ['unpaid', 'FC1E1-001'],
    ['pending', 'FC1E1-003'],
    ['verified', 'FC1E1-002'],
]);

test('event orders list can filter orders with due balance', function () {
    ['event' => $event] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'has_due' => '1',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 2)
                ->where('filters.has_due', true)
                ->where('orders.data', fn ($orders) => collect($orders)
                    ->pluck('order_number')
                    ->sort()
                    ->values()
                    ->all() === ['FC1E1-001', 'FC1E1-003']),
        );
});

test('pickup point summary includes package quantities per hub', function () {
    ['event' => $event, 'pickupA' => $pickupA, 'pickupB' => $pickupB, 'orders' => $orders] = createEventWithFilterableOrders();

    $pkg = $event->packages()->create([
        'name' => 'Oil 1kg',
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 1,
        'package_price' => 200,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['confirmed_verified']->id,
        'event_package_id' => $pkg->id,
        'quantity' => 3,
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 1,
        'package_price' => 200,
        'line_total' => 600,
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('orderSummary.pickup_points.0.id', $pickupA->id)
                ->where('orderSummary.pickup_points.0.packages', [])
                ->where('orderSummary.pickup_points.1.id', $pickupB->id)
                ->where('orderSummary.pickup_points.1.packages', [
                    [
                        'id' => $pkg->id,
                        'name' => 'Oil 1kg',
                        'quantity' => 3,
                        'unit_label' => '1 kg',
                        'pack_line_label' => '3 × 1 kg = 3 kg',
                    ],
                ]),
        );
});

test('event orders summary includes package order counts by status', function () {
    ['event' => $event, 'orders' => $orders] = createEventWithFilterableOrders();

    $pkg = $event->packages()->create([
        'name' => 'Rice 5kg',
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 5,
        'package_price' => 500,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['pending_unpaid']->id,
        'event_package_id' => $pkg->id,
        'quantity' => 1,
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 5,
        'package_price' => 500,
        'line_total' => 500,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['confirmed_verified']->id,
        'event_package_id' => $pkg->id,
        'quantity' => 2,
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 5,
        'package_price' => 500,
        'line_total' => 1000,
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('orderSummary.packages.0.name', 'Rice 5kg')
                ->where('orderSummary.packages.0.order_count', 1)
                ->where('orderSummary.packages.0.pack_count', 2)
                ->where('orderSummary.packages.0.physical_label', '10 kg')
                ->where('orderSummary.packages.0.pack_line_label', '2 × 5 kg = 10 kg')
                ->where('orderSummary.packages.0.by_status.pending', 1)
                ->where('orderSummary.packages.0.by_status.confirmed', 1)
                ->where('orderSummary.packages.0.by_status.delivered', 0),
        );
});

test('event orders summary cards include package physical totals', function () {
    ['event' => $event, 'orders' => $orders] = createEventWithFilterableOrders();

    $pkg = $event->packages()->create([
        'name' => 'Ghee 3kg',
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 3,
        'package_price' => 1500,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['confirmed_verified']->id,
        'event_package_id' => $pkg->id,
        'quantity' => 2,
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 3,
        'package_price' => 1500,
        'line_total' => 3000,
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', $event))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('summary.packages.0.name', 'Ghee 3kg')
                ->where('summary.packages.0.pack_count', 2)
                ->where('summary.packages.0.physical_label', '6 kg')
                ->where('summary.packages.0.pack_line_label', '2 × 3 kg = 6 kg'),
        );
});

test('event orders list shows per-package pack lines', function () {
    ['event' => $event, 'orders' => $orders] = createEventWithFilterableOrders();

    $pkg3kg = $event->packages()->create([
        'name' => 'Ghee 3kg',
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 3,
        'package_price' => 1500,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    $pkg5kg = $event->packages()->create([
        'name' => 'Ghee 5kg',
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 5,
        'package_price' => 2400,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['pending_unpaid']->id,
        'event_package_id' => $pkg3kg->id,
        'quantity' => 2,
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 3,
        'package_price' => 1500,
        'line_total' => 3000,
    ]);

    EventOrderItem::query()->create([
        'event_order_id' => $orders['pending_unpaid']->id,
        'event_package_id' => $pkg5kg->id,
        'quantity' => 4,
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 5,
        'package_price' => 2400,
        'line_total' => 9600,
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'search' => 'FC1E1-001',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.package_lines', [
                    ['line_label' => '3kg*2=6kg'],
                    ['line_label' => '5kg*4=20kg'],
                ]),
        );
});

test('event orders list can filter by pickup point and date range', function () {
    ['event' => $event, 'pickupB' => $pickupB] = createEventWithFilterableOrders();

    $admin = User::factory()->create(['role' => 'admin']);

    $fromDate = now()->subDays(2)->toDateString();
    $toDate = now()->toDateString();

    actingAs($admin)
        ->get(route('admin.events.orders.index', [
            'fundCycleEvent' => $event,
            'pickup_point_id' => $pickupB->id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'FC1E1-002')
                ->where('filters.pickup_point_id', (string) $pickupB->id)
                ->where('filters.from_date', $fromDate)
                ->where('filters.to_date', $toDate),
        );
});
