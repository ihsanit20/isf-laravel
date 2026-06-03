<?php

use App\Enums\EventOrderStatus;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
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
                ->has('filters')
                ->has('filterOptions.statuses', 4)
                ->has('filterOptions.payment_statuses', 4)
                ->has('filterOptions.pickup_points', 2),
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
