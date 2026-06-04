<?php

use App\Enums\DepositSubmissionStatus;
use App\Enums\EventExpenseCategory;
use App\Enums\FundCycleEventStatus;
use App\Enums\MemberStatus;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\DepositSubmission;
use App\Models\EventBankWithdrawal;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleEvent;
use App\Models\GeneralExpense;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * @return array{event: FundCycleEvent, eventB: FundCycleEvent}
 */
function createTreasuryTestEvents(): array
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Treasury Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->subMonth()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Treasury Event A',
        'slug' => 'treasury-event-a-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $eventB = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Treasury Event B',
        'slug' => 'treasury-event-b-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    return ['event' => $event, 'eventB' => $eventB];
}

test('deposits page current balance reflects bank outflows not event expenses or allocations', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 100_000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'TREASURY-01',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/treasury-proof.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
        'verified_by_user_id' => $admin->id,
    ]);

    GeneralExpense::query()->create([
        'expense_date' => '2026-06-01',
        'category' => 'other',
        'amount' => 1_000,
        'created_by_user_id' => $admin->id,
    ]);

    ['event' => $event, 'eventB' => $eventB] = createTreasuryTestEvents();

    $event->bankWithdrawals()->create([
        'withdrawal_date' => '2026-06-02',
        'amount' => 15_000,
        'reference_no' => 'CHQ-123',
        'created_by_user_id' => $admin->id,
    ]);

    $eventB->bankWithdrawals()->create([
        'withdrawal_date' => '2026-06-02',
        'amount' => 10_000,
        'reference_no' => 'CHQ-123',
        'created_by_user_id' => $admin->id,
    ]);

    $event->expenses()->create([
        'expense_date' => '2026-06-03',
        'category' => EventExpenseCategory::Transport,
        'amount' => 45,
        'created_by_user_id' => $admin->id,
    ]);

    $memberUser = User::factory()->create();
    $member = Member::factory()->for($memberUser, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'units' => 1,
    ]);

    FundCycleAllocation::query()->create([
        'fund_cycle_id' => $event->fund_cycle_id,
        'member_id' => $member->id,
        'amount' => 50_000,
        'allocated_at' => now(),
        'created_by_user_id' => $admin->id,
    ]);

    $category = ChargeCategory::query()->create([
        'title' => 'Test Fee',
        'code' => 'test_fee_'.uniqid(),
        'default_amount' => 500,
        'is_active' => true,
    ]);

    $charge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 500,
        'status' => Charge::STATUS_POSTED,
        'effective_at' => now(),
    ]);

    ChargeAllocation::query()->create([
        'charge_id' => $charge->id,
        'amount' => 500,
        'confirmed_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.deposits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.verified_amount', 100_000)
            ->where('summary.total_general_expense', 1_000)
            ->where('summary.total_event_bank_withdrawals', 25_000)
            ->where('summary.total_charge_settlements', 500)
            ->where('summary.current_balance', 73_500));
});

test('admins can record bank withdrawal for an event', function () {
    ['event' => $event] = createTreasuryTestEvents();
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin);

    \Pest\Laravel\post(route('admin.events.bank-withdrawals.store', $event), [
        'withdrawal_date' => '2026-06-04',
        'amount' => 10_000,
        'description' => 'Investment float',
        'reference_no' => 'CHQ-999',
    ])->assertRedirect(route('admin.events.show', [
        'fundCycleEvent' => $event,
        'tab' => 'withdrawals',
    ]));

    $withdrawal = EventBankWithdrawal::query()->firstOrFail();

    expect($withdrawal->fund_cycle_event_id)->toBe($event->id)
        ->and($withdrawal->amount)->toBe(10_000);
});

test('event details shows float summary and bank withdrawals', function () {
    ['event' => $event] = createTreasuryTestEvents();
    $admin = User::factory()->create(['role' => 'admin']);

    $event->bankWithdrawals()->create([
        'withdrawal_date' => '2026-06-01',
        'amount' => 10_000,
        'created_by_user_id' => $admin->id,
    ]);

    $event->expenses()->create([
        'expense_date' => '2026-06-02',
        'category' => EventExpenseCategory::Transport,
        'amount' => 45,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('event.bank_withdrawals', 1)
            ->where('event.float_summary.withdrawn_from_bank', 10_000)
            ->where('event.float_summary.logged_expenses', 45)
            ->where('event.float_summary.remaining_float', 9_955)
            ->where('event.float_summary.is_over_logged', false));
});

test('cannot update bank withdrawal from another event', function () {
    ['event' => $event, 'eventB' => $eventB] = createTreasuryTestEvents();
    $admin = User::factory()->create(['role' => 'admin']);

    $withdrawal = $eventB->bankWithdrawals()->create([
        'withdrawal_date' => '2026-06-01',
        'amount' => 5_000,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    \Pest\Laravel\post(route('admin.events.bank-withdrawals.update', [$event, $withdrawal]), [
        '_method' => 'PUT',
        'withdrawal_date' => '2026-06-05',
        'amount' => 6_000,
    ])->assertNotFound();
});
