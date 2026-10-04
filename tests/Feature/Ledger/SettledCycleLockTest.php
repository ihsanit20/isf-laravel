<?php

require_once __DIR__.'/LedgerTestHelpers.php';

use App\Ledger\Postings\CyclePostings;
use App\Ledger\Postings\MemberPostings;
use App\Models\FundCycle;
use App\Models\Member;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

/**
 * @return array{admin: User, user: User, member: Member, cycle: FundCycle}
 */
function settledCycle(): array
{
    $setup = fundedCycle();
    app(CyclePostings::class)->settle($setup['cycle'], $setup['admin']);

    return [...$setup, 'cycle' => $setup['cycle']->refresh()];
}

test('a settled cycle cannot be edited', function () {
    ['admin' => $admin, 'cycle' => $cycle] = settledCycle();

    actingAs($admin)
        ->put(route('admin.fund-cycles.update', $cycle), [
            'name' => 'Renamed',
            'status' => FundCycle::STATUS_OPEN,
            'unit_amount' => $cycle->unit_amount,
            'start_date' => $cycle->start_date->toDateString(),
            'slots' => $cycle->slots,
        ])
        ->assertForbidden();

    expect($cycle->refresh()->name)->not->toBe('Renamed')
        ->and($cycle->status)->toBe(FundCycle::STATUS_SETTLED);
});

test('a settled cycle takes no new event, business or cycle entry', function () {
    ['admin' => $admin, 'cycle' => $cycle] = settledCycle();

    actingAs($admin)
        ->post(route('admin.fund-cycles.events.store', $cycle), [
            'title' => 'Late Event',
            'status' => 'draft',
            'order_open_at' => '2026-01-01 00:00:00',
            'order_close_at' => '2026-12-31 00:00:00',
        ])
        ->assertForbidden();

    actingAs($admin)
        ->post(route('admin.businesses.store'), [
            'fund_cycle_id' => $cycle->id,
            'title' => 'Late Business',
        ])
        ->assertSessionHasErrors('fund_cycle_id');

    actingAs($admin)
        ->post(route('admin.fund-cycles.transactions.store', $cycle), [
            'direction' => 'expense',
            'category' => 'documentation',
            'amount' => 100,
            'transaction_date' => '2026-03-05',
        ])
        ->assertForbidden();

    expect($cycle->events()->count())->toBe(0)
        ->and($cycle->investments()->count())->toBe(0);
});

test('no capital can be allocated to a settled cycle', function () {
    ['admin' => $admin, 'user' => $user, 'member' => $member, 'cycle' => $cycle] = settledCycle();

    app(MemberPostings::class)->allocateToCycle([
        'fund_cycle_id' => $cycle->id,
        'member_id' => $member->id,
        'slot_key' => 'S1',
        'amount' => 1000,
        'allocated_at' => now(),
        'created_by_user_id' => $admin->id,
    ], $user->id);
})->throws(HttpException::class, 'This fund cycle has been settled and is locked for changes.');

test('the cycle form cannot mark a cycle settled', function () {
    ['admin' => $admin, 'cycle' => $cycle] = fundedCycle();

    actingAs($admin)
        ->put(route('admin.fund-cycles.update', $cycle), [
            'name' => $cycle->name,
            'status' => FundCycle::STATUS_SETTLED,
            'unit_amount' => $cycle->unit_amount,
            'start_date' => $cycle->start_date->toDateString(),
            'slots' => $cycle->slots,
        ])
        ->assertSessionHasErrors('status');

    expect($cycle->refresh()->status)->toBe(FundCycle::STATUS_OPEN)
        ->and(FundCycle::editableStatuses())->not->toContain(FundCycle::STATUS_SETTLED);
});
