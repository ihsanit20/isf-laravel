<?php

require_once __DIR__.'/LedgerTestHelpers.php';

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Postings\MemberPostings;
use App\Models\JournalEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Deposit 10,000 (reversed afterwards) and a 6,000 cycle allocation.
 *
 * @return array{admin: User, user: User, deposit: JournalEntry, reversal: JournalEntry, allocation: JournalEntry}
 */
function journalFixture(): array
{
    $admin = ledgerAdmin();
    $user = User::factory()->create(['name' => 'Rahim Uddin']);
    $cycle = ledgerCycle($admin);
    $member = ledgerMember($user, 1);
    ledgerVerifiedDeposit($user, 10000);

    app(MemberPostings::class)->allocateToCycle([
        'fund_cycle_id' => $cycle->id,
        'member_id' => $member->id,
        'slot_key' => 'S1',
        'amount' => 6000,
        'allocated_at' => now(),
        'created_by_user_id' => $admin->id,
    ], $user->id);

    $deposit = JournalEntry::query()->where('kind', 'deposit_verified')->firstOrFail();
    $allocation = JournalEntry::query()->where('kind', 'cycle_allocated')->firstOrFail();
    $reversal = app(Ledger::class)->reverse($deposit, 'Test correction');

    return compact('admin', 'user', 'deposit', 'reversal', 'allocation');
}

test('non-admins cannot open the journal', function () {
    actingAs(User::factory()->create())
        ->get(route('admin.accounts.journal'))
        ->assertForbidden();
});

test('journal lists every entry with summary and filter options', function () {
    ['admin' => $admin] = journalFixture();

    actingAs($admin)
        ->get(route('admin.accounts.journal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Journal')
            ->has('entries.data', 3)
            ->where('summary.entries', 3)
            ->where('summary.matched', null)
            ->has('options.kinds', 3)
            ->has('options.users', 1));
});

test('account filter reports the net movement of matching lines', function () {
    ['admin' => $admin] = journalFixture();

    actingAs($admin)
        ->get(route('admin.accounts.journal', ['account' => Account::MemberBalance->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 3)
            ->where('summary.matched.lines', 3)
            ->where('summary.matched.debit', 16000)
            ->where('summary.matched.credit', 10000)
            ->where('summary.matched.net', 6000)
            ->where('entries.data.0.lines', fn ($lines) => collect($lines)->contains('matches', true)));
});

test('status filter separates active, reversed and reversal entries', function () {
    ['admin' => $admin, 'deposit' => $deposit, 'reversal' => $reversal, 'allocation' => $allocation] = journalFixture();

    actingAs($admin);

    $this->get(route('admin.accounts.journal', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.id', $allocation->id));

    $this->get(route('admin.accounts.journal', ['status' => 'reversed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.id', $deposit->id)
            ->where('entries.data.0.reversed_by_id', $reversal->id));

    $this->get(route('admin.accounts.journal', ['status' => 'reversal']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.reversal_of_id', $deposit->id));
});

test('search finds entries by id, people and text', function () {
    ['admin' => $admin, 'deposit' => $deposit] = journalFixture();

    actingAs($admin);

    $this->get(route('admin.accounts.journal', ['search' => '#'.$deposit->id]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 2));

    $this->get(route('admin.accounts.journal', ['search' => 'Rahim']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 3));

    $this->get(route('admin.accounts.journal', ['search' => 'allocated to']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));

    $this->get(route('admin.accounts.journal', ['search' => 'no-such-thing']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));
});

test('kind, amount and sort filters narrow the list', function () {
    ['admin' => $admin, 'allocation' => $allocation] = journalFixture();

    actingAs($admin);

    $this->get(route('admin.accounts.journal', ['kind' => 'cycle_allocated']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.source.label', 'Fund Cycle Allocation #'.$allocation->source_id));

    $this->get(route('admin.accounts.journal', ['max_amount' => 11000]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 2));

    $this->get(route('admin.accounts.journal', ['sort' => 'amount_desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.id', $allocation->id)
            ->where('entries.data.0.total', 12000));
});

test('export downloads the filtered journal lines as CSV', function () {
    ['admin' => $admin, 'allocation' => $allocation] = journalFixture();

    $response = actingAs($admin)
        ->get(route('admin.accounts.journal.export', ['kind' => 'cycle_allocated']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

    expect($rows)->toHaveCount(5)
        ->and($rows[1][0])->toBe((string) $allocation->id)
        ->and($rows[1][2])->toBe('cycle_allocated');
});
