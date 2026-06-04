<?php

use App\Enums\FundCycleEventStatus;
use App\Models\EventBankDeposit;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;

/**
 * @return array{event: FundCycleEvent, otherEvent: FundCycleEvent, admin: User}
 */
function createEventsForBankDepositTests(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Bank Deposit Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->subMonth()->toDateString(),
        'created_by_user_id' => $owner->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Bank Deposit Event',
        'slug' => 'bank-deposit-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $otherEvent = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Other Bank Deposit Event',
        'slug' => 'other-bank-deposit-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    return compact('event', 'otherEvent', 'admin');
}

test('admins see event bank deposits on the event details page', function () {
    ['event' => $event, 'admin' => $admin] = createEventsForBankDepositTests();

    $deposit = $event->bankDeposits()->create([
        'deposit_date' => '2026-06-10',
        'amount' => 12_000,
        'description' => 'Closing balance',
        'reference_no' => 'DEP-001',
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/EventDetails')
            ->has('event.bank_deposits', 1)
            ->where('event.bank_deposits.0.id', $deposit->id)
            ->where('event.bank_deposit_summary.total_amount', 12_000)
            ->where('event.bank_deposit_summary.entry_count', 1));
});

test('members cannot view event details with bank deposits', function () {
    ['event' => $event] = createEventsForBankDepositTests();

    $member = User::factory()->create(['role' => 'member']);

    actingAs($member)
        ->get(route('admin.events.show', $event))
        ->assertForbidden();
});

test('admins can create update and delete event bank deposits', function () {
    ['event' => $event, 'admin' => $admin] = createEventsForBankDepositTests();

    actingAs($admin);

    post(route('admin.events.bank-deposits.store', $event), [
        'deposit_date' => '2026-06-11',
        'amount' => 8_000,
        'description' => 'First deposit',
        'reference_no' => 'DEP-100',
    ])->assertRedirect(route('admin.events.show', [
        'fundCycleEvent' => $event,
        'tab' => 'deposits',
    ]));

    $deposit = EventBankDeposit::query()->firstOrFail();

    expect($deposit->fund_cycle_event_id)->toBe($event->id)
        ->and($deposit->amount)->toBe(8_000);

    post(route('admin.events.bank-deposits.update', [$event, $deposit]), [
        '_method' => 'PUT',
        'deposit_date' => '2026-06-12',
        'amount' => 9_000,
        'description' => 'Updated deposit',
    ])->assertRedirect(route('admin.events.show', [
        'fundCycleEvent' => $event,
        'tab' => 'deposits',
    ]));

    expect($deposit->refresh()->amount)->toBe(9_000);

    delete(route('admin.events.bank-deposits.destroy', [$event, $deposit]))
        ->assertRedirect(route('admin.events.show', [
            'fundCycleEvent' => $event,
            'tab' => 'deposits',
        ]));

    expect(EventBankDeposit::query()->count())->toBe(0);
});

test('cannot update bank deposit from another event', function () {
    ['event' => $event, 'otherEvent' => $otherEvent, 'admin' => $admin] = createEventsForBankDepositTests();

    $deposit = $otherEvent->bankDeposits()->create([
        'deposit_date' => '2026-06-01',
        'amount' => 5_000,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    post(route('admin.events.bank-deposits.update', [$event, $deposit]), [
        '_method' => 'PUT',
        'deposit_date' => '2026-06-05',
        'amount' => 6_000,
    ])->assertNotFound();
});

test('event details shows bank deposit reconciliation hint', function () {
    ['event' => $event, 'admin' => $admin] = createEventsForBankDepositTests();

    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'order_number' => 'FC1E1-500',
        'customer_name' => 'Buyer',
        'customer_phone' => '01750000000',
        'status' => 'confirmed',
        'total_amount' => 2000,
        'advance_amount' => 500,
    ]);

    EventPayment::query()->create([
        'event_order_id' => $order->id,
        'amount' => 500,
        'payment_method' => 'bkash',
        'payment_status' => 'verified',
    ]);

    $event->bankDeposits()->create([
        'deposit_date' => '2026-06-10',
        'amount' => 200,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.bank_deposit_reconciliation.verified_customer_payments', 500)
            ->where('event.bank_deposit_reconciliation.deposited_to_bank', 200)
            ->where('event.bank_deposit_reconciliation.not_yet_deposited', 300));
});
