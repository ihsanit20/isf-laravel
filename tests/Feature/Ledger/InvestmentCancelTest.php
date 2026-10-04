<?php

require_once __DIR__.'/LedgerTestHelpers.php';

use App\Enums\EventOrderStatus;
use App\Enums\FundCycleEventStatus;
use App\Ledger\Money;
use App\Ledger\Postings\CyclePostings;
use App\Ledger\Postings\InvestmentPostings;
use App\Ledger\Postings\MemberPostings;
use App\Models\BusinessTransaction;
use App\Models\CycleInvestment;
use App\Models\EventBankWithdrawal;
use App\Models\EventOrder;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * @return array{admin: User, user: User, cycle: FundCycle}
 */
function cancelTestCycle(int $amount = 100000): array
{
    $admin = ledgerAdmin();
    $user = ledgerAdmin();
    $cycle = ledgerCycle($admin);
    $member = ledgerMember($user, 1);
    ledgerVerifiedDeposit($user, $amount);

    app(MemberPostings::class)->allocateToCycle([
        'fund_cycle_id' => $cycle->id, 'member_id' => $member->id, 'slot_key' => 'S1',
        'amount' => $amount, 'allocated_at' => now(), 'created_by_user_id' => $admin->id,
    ], $user->id);

    return ['admin' => $admin, 'user' => $user, 'cycle' => $cycle];
}

function cancelTestOrder(FundCycleEvent $event, EventOrderStatus $status): EventOrder
{
    return EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id,
        'order_number' => 'ISF-'.uniqid(),
        'customer_name' => 'Customer',
        'customer_phone' => '01700000000',
        'status' => $status,
        'total_amount' => 1000,
        'advance_amount' => 500,
    ]);
}

test('an untouched event can be cancelled and no longer blocks settlement', function () {
    ['admin' => $admin, 'user' => $user, 'cycle' => $cycle] = cancelTestCycle();
    $event = ledgerEvent($cycle);

    expect(app(CyclePostings::class)->summary($cycle)['blockers'])->not->toBe([]);

    actingAs($admin)
        ->patch(route('admin.events.cancel', $event))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.events.show', $event));

    expect($event->refresh()->status)->toBe(FundCycleEventStatus::Cancelled)
        ->and($event->investment->status)->toBe(CycleInvestment::STATUS_CANCELLED)
        ->and(app(CyclePostings::class)->summary($cycle)['investments'])->toBe([])
        ->and(app(CyclePostings::class)->summary($cycle)['blockers'])->toBe([]);

    app(CyclePostings::class)->settle($cycle, $admin);

    expect(app(MemberPostings::class)->availableBalance($user->id))->toBe(Money::toPaisa(100000));
});

test('an event with a live order cannot be cancelled until the order is cancelled', function () {
    ['admin' => $admin, 'cycle' => $cycle] = cancelTestCycle();
    $event = ledgerEvent($cycle);
    $order = cancelTestOrder($event, EventOrderStatus::Pending);

    actingAs($admin)
        ->patch(route('admin.events.cancel', $event))
        ->assertSessionHasErrors('cancel');

    expect($event->refresh()->investment->status)->toBe(CycleInvestment::STATUS_ACTIVE);

    $order->update(['status' => EventOrderStatus::Cancelled]);

    actingAs($admin)
        ->patch(route('admin.events.cancel', $event))
        ->assertSessionHasNoErrors();

    expect($event->refresh()->investment->status)->toBe(CycleInvestment::STATUS_CANCELLED);
});

test('an event cannot be cancelled once money has moved', function () {
    ['admin' => $admin, 'cycle' => $cycle] = cancelTestCycle();
    $event = ledgerEvent($cycle);

    app(InvestmentPostings::class)->eventWithdrawal(EventBankWithdrawal::query()->create([
        'fund_cycle_event_id' => $event->id, 'withdrawal_date' => '2026-02-01', 'amount' => 1000,
    ]));

    actingAs($admin)
        ->patch(route('admin.events.cancel', $event))
        ->assertSessionHasErrors('cancel');

    expect($event->refresh()->status)->toBe(FundCycleEventStatus::Published)
        ->and($event->investment->status)->toBe(CycleInvestment::STATUS_ACTIVE);
});

test('a cancelled event is locked and cannot be cancelled again', function () {
    ['admin' => $admin, 'cycle' => $cycle] = cancelTestCycle();
    $event = ledgerEvent($cycle);

    actingAs($admin)->patch(route('admin.events.cancel', $event));

    actingAs($admin)
        ->put(route('admin.events.update', $event), [
            'title' => 'Renamed',
            'status' => 'published',
            'order_open_at' => '2026-01-01 00:00:00',
            'order_close_at' => '2026-12-31 00:00:00',
        ])
        ->assertForbidden();

    actingAs($admin)
        ->patch(route('admin.events.cancel', $event))
        ->assertForbidden();
});

test('the event form cannot set the cancelled status', function () {
    ['admin' => $admin, 'cycle' => $cycle] = cancelTestCycle();
    $event = ledgerEvent($cycle);

    actingAs($admin)
        ->put(route('admin.events.update', $event), [
            'title' => $event->title,
            'status' => 'cancelled',
            'order_open_at' => '2026-01-01 00:00:00',
            'order_close_at' => '2026-12-31 00:00:00',
        ])
        ->assertSessionHasErrors('status');

    expect($event->refresh()->status)->toBe(FundCycleEventStatus::Published);
});

test('a business can be cancelled only before any transaction', function () {
    ['admin' => $admin, 'cycle' => $cycle] = cancelTestCycle();
    $investments = app(InvestmentPostings::class);

    $untouched = CycleInvestment::query()->create(['fund_cycle_id' => $cycle->id, 'type' => 'business', 'title' => 'Empty']);

    actingAs($admin)
        ->patch(route('admin.businesses.cancel', $untouched))
        ->assertSessionHasNoErrors();

    expect($untouched->refresh()->status)->toBe(CycleInvestment::STATUS_CANCELLED);

    $used = CycleInvestment::query()->create(['fund_cycle_id' => $cycle->id, 'type' => 'business', 'title' => 'Used']);

    foreach ([['invest', 1000], ['capital_return', 1000]] as [$type, $amount]) {
        $investments->businessTransaction(BusinessTransaction::query()->create([
            'cycle_investment_id' => $used->id, 'type' => $type, 'amount' => $amount, 'transaction_date' => '2026-03-01',
        ]));
    }

    actingAs($admin)
        ->patch(route('admin.businesses.cancel', $used))
        ->assertSessionHasErrors('cancel');

    expect($used->refresh()->status)->toBe(CycleInvestment::STATUS_ACTIVE)
        ->and($investments->cancelBlockers($used))->not->toBe([]);
});
