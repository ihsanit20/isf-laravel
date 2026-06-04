<?php

use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * @return array{event: FundCycleEvent, order: EventOrder, admin: User}
 */
function createEventForPaymentLogTests(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Payment Log Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->subMonth()->toDateString(),
        'created_by_user_id' => $owner->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Payment Log Event',
        'slug' => 'payment-log-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'order_number' => 'FC1E1-100',
        'customer_name' => 'Rahim Uddin',
        'customer_phone' => '01710000000',
        'status' => 'confirmed',
        'total_amount' => 1500,
        'advance_amount' => 500,
    ]);

    return compact('event', 'order', 'admin');
}

test('admins see event payment log on the event details page', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createEventForPaymentLogTests();

    $verified = EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 500,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
        'paid_at' => now()->subDay(),
        'verified_at' => now()->subDay(),
        'verified_by_user_id' => $admin->id,
    ]);

    $pending = EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 200,
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'paid_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/EventDetails')
            ->has('event.payments', 2)
            ->where('event.payment_summary.entry_count', 2)
            ->where('event.payment_summary.verified_amount', 500)
            ->where('event.payment_summary.verified_count', 1)
            ->where('event.payment_summary.pending_count', 1)
            ->where('event.payment_summary.failed_count', 0)
            ->where('event.payments.0.id', $pending->id)
            ->where('event.payments.0.order_id', $order->id)
            ->where('event.payments.0.order_number', 'FC1E1-100')
            ->where('event.payments.0.customer_name', 'Rahim Uddin')
            ->where('event.payments.0.payment_status_label', 'Pending')
            ->where('event.payments.1.id', $verified->id)
            ->where('event.payments.1.payment_status_label', 'Verified'));
});

test('event payment log only includes payments for that event', function () {
    ['event' => $event, 'order' => $order, 'admin' => $admin] = createEventForPaymentLogTests();

    $otherEvent = FundCycleEvent::query()->create([
        'fund_cycle_id' => $event->fund_cycle_id,
        'title' => 'Other Payment Event',
        'slug' => 'other-payment-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $otherOrder = EventOrder::query()->create([
        'fund_cycle_event_id' => $otherEvent->id,
        'order_number' => 'FC1E1-200',
        'customer_name' => 'Other Buyer',
        'customer_phone' => '01720000000',
        'status' => 'pending',
        'total_amount' => 1000,
        'advance_amount' => 100,
    ]);

    EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 100,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
    ]);

    EventPayment::query()->create([
        'event_order_id' => $otherOrder->id,
        'amount' => 999,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
    ]);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('event.payments', 1)
            ->where('event.payments.0.amount', 100)
            ->where('event.payment_summary.verified_amount', 100));
});
