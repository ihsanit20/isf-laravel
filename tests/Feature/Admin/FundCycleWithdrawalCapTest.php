<?php

use App\Enums\FundCycleEventStatus;
use App\Enums\MemberStatus;
use App\Models\EventBankWithdrawal;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleEvent;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * @return array{
 *     cycle: FundCycle,
 *     event: FundCycleEvent,
 *     eventB: FundCycleEvent,
 *     admin: User,
 *     member: Member,
 * }
 */
function createCycleWithAllocation(int $allocatedAmount = 50_000): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Cap Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->subMonth()->toDateString(),
        'created_by_user_id' => $owner->id,
    ]);

    $member = Member::factory()->for($owner, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'units' => 1,
    ]);

    if ($allocatedAmount > 0) {
        FundCycleAllocation::query()->create([
            'fund_cycle_id' => $cycle->id,
            'member_id' => $member->id,
            'amount' => $allocatedAmount,
            'allocated_at' => now(),
            'created_by_user_id' => $admin->id,
        ]);
    }

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Cap Event A',
        'slug' => 'cap-event-a-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $eventB = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Cap Event B',
        'slug' => 'cap-event-b-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    return compact('cycle', 'event', 'eventB', 'admin', 'member');
}

test('bank withdrawals across events cannot exceed fund cycle allocation total', function () {
    ['event' => $event, 'eventB' => $eventB, 'admin' => $admin] = createCycleWithAllocation(50_000);

    actingAs($admin);

    post(route('admin.events.bank-withdrawals.store', $event), [
        'withdrawal_date' => '2026-06-01',
        'amount' => 15_000,
        'reference_no' => 'CHQ-123',
    ])->assertRedirect();

    post(route('admin.events.bank-withdrawals.store', $eventB), [
        'withdrawal_date' => '2026-06-01',
        'amount' => 10_000,
        'reference_no' => 'CHQ-123',
    ])->assertRedirect();

    expect((int) EventBankWithdrawal::query()->sum('amount'))->toBe(25_000);

    post(route('admin.events.bank-withdrawals.store', $event), [
        'withdrawal_date' => '2026-06-02',
        'amount' => 30_000,
    ])->assertSessionHasErrors('amount');
});

test('bank withdrawal is blocked when fund cycle has no allocations', function () {
    ['event' => $event, 'admin' => $admin] = createCycleWithAllocation(0);

    actingAs($admin);

    post(route('admin.events.bank-withdrawals.store', $event), [
        'withdrawal_date' => '2026-06-01',
        'amount' => 5_000,
    ])->assertSessionHasErrors('amount');

    expect(EventBankWithdrawal::query()->count())->toBe(0);
});

test('event details includes cycle withdrawal budget', function () {
    ['event' => $event, 'eventB' => $eventB, 'admin' => $admin] = createCycleWithAllocation(50_000);

    $event->bankWithdrawals()->create([
        'withdrawal_date' => '2026-06-01',
        'amount' => 15_000,
        'created_by_user_id' => $admin->id,
    ]);

    $eventB->bankWithdrawals()->create([
        'withdrawal_date' => '2026-06-01',
        'amount' => 10_000,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.cycle_withdrawal_budget.allocated_amount', 50_000)
            ->where('event.cycle_withdrawal_budget.withdrawn_amount', 25_000)
            ->where('event.cycle_withdrawal_budget.remaining_amount', 25_000));
});

test('updating a withdrawal respects the fund cycle cap', function () {
    ['event' => $event, 'admin' => $admin] = createCycleWithAllocation(20_000);

    $withdrawal = $event->bankWithdrawals()->create([
        'withdrawal_date' => '2026-06-01',
        'amount' => 10_000,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    post(route('admin.events.bank-withdrawals.update', [$event, $withdrawal]), [
        '_method' => 'PUT',
        'withdrawal_date' => '2026-06-01',
        'amount' => 25_000,
    ])->assertSessionHasErrors('amount');

    post(route('admin.events.bank-withdrawals.update', [$event, $withdrawal]), [
        '_method' => 'PUT',
        'withdrawal_date' => '2026-06-01',
        'amount' => 18_000,
    ])->assertRedirect(route('admin.events.show', [
        'fundCycleEvent' => $event,
        'tab' => 'withdrawals',
    ]));

    expect($withdrawal->refresh()->amount)->toBe(18_000);
});
