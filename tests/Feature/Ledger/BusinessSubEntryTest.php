<?php

require_once __DIR__.'/LedgerTestHelpers.php';

use App\Ledger\InvestmentReport;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\BusinessSubEntry;
use App\Models\BusinessTransaction;
use App\Models\CycleInvestment;
use App\Models\JournalEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function subLedgerBusiness(): array
{
    ['admin' => $admin, 'cycle' => $cycle] = fundedCycle();

    $business = CycleInvestment::query()->create([
        'fund_cycle_id' => $cycle->id,
        'type' => CycleInvestment::TYPE_BUSINESS,
        'title' => 'Cattle',
    ]);

    app(InvestmentPostings::class)->businessTransaction(BusinessTransaction::query()->create([
        'cycle_investment_id' => $business->id,
        'type' => 'invest',
        'amount' => 50000,
        'transaction_date' => '2026-03-01',
    ]));

    return [$admin, $business];
}

test('sub entries are note only and never touch the journal', function () {
    [$admin, $business] = subLedgerBusiness();
    $report = app(InvestmentReport::class);
    $journalCount = JournalEntry::query()->count();
    $before = $report->for($business);

    $rows = [
        ['expense', 'গরু কেনা', 34000],
        ['expense', 'ডাক্তার', 1000],
        ['expense', 'খাবার', 3000],
        ['expense', 'খাবার', 3300],
        ['expense', 'খাবার', 3200],
        ['income', 'বিক্রি', 54500],
    ];

    foreach ($rows as [$direction, $category, $amount]) {
        actingAs($admin)
            ->post(route('admin.businesses.sub-entries.store', $business), [
                'direction' => $direction,
                'category' => $category,
                'amount' => $amount,
                'entry_date' => '2026-04-01',
            ])
            ->assertSessionHasNoErrors();
    }

    expect(JournalEntry::query()->count())->toBe($journalCount)
        ->and($report->for($business->refresh()))->toEqual($before);

    actingAs($admin)
        ->get(route('admin.businesses.show', $business))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subEntries', 6)
            ->where('subSummary.total_expense', 44500)
            ->where('subSummary.total_income', 54500)
            ->where('subSummary.result', 10000)
            ->where('subSummary.capital_given', 50000)
            ->where('subSummary.unspent', 5500)
            ->where('subSummary.by_category.0.category', 'গরু কেনা')
            ->where('subSummary.by_category.1.category', 'খাবার')
            ->where('subSummary.by_category.1.total', 9500)
            ->where('subSummary.by_category.1.count', 3)
            ->etc());
});

test('admin can update and delete a sub entry', function () {
    [$admin, $business] = subLedgerBusiness();
    $entry = $business->subEntries()->create([
        'direction' => 'expense', 'category' => 'খাবার', 'amount' => 3000, 'entry_date' => '2026-04-01',
    ]);

    actingAs($admin)
        ->put(route('admin.businesses.sub-entries.update', [$business, $entry]), [
            'direction' => 'expense', 'category' => 'খাবার', 'amount' => 3500, 'entry_date' => '2026-04-02', 'note' => 'April',
        ])
        ->assertSessionHasNoErrors();

    expect((float) $entry->refresh()->amount)->toBe(3500.0)
        ->and($entry->note)->toBe('April');

    actingAs($admin)
        ->delete(route('admin.businesses.sub-entries.destroy', [$business, $entry]))
        ->assertSessionHasNoErrors();

    expect(BusinessSubEntry::query()->count())->toBe(0);
});

test('sub entries stay editable after the business is closed', function () {
    [$admin, $business] = subLedgerBusiness();
    $business->update(['status' => CycleInvestment::STATUS_CLOSED]);

    actingAs($admin)
        ->post(route('admin.businesses.sub-entries.store', $business), [
            'direction' => 'income', 'category' => 'বিক্রি', 'amount' => 100, 'entry_date' => '2026-05-01',
        ])
        ->assertSessionHasNoErrors();

    expect($business->subEntries()->count())->toBe(1);
});

test('non admins cannot manage sub entries', function () {
    [, $business] = subLedgerBusiness();

    actingAs(User::factory()->create())
        ->post(route('admin.businesses.sub-entries.store', $business), [
            'direction' => 'expense', 'category' => 'খাবার', 'amount' => 100, 'entry_date' => '2026-05-01',
        ])
        ->assertForbidden();
});

test('sub entries are scoped to their own business', function () {
    [$admin, $business] = subLedgerBusiness();
    $other = CycleInvestment::query()->create([
        'fund_cycle_id' => $business->fund_cycle_id, 'type' => CycleInvestment::TYPE_BUSINESS, 'title' => 'Goats',
    ]);
    $event = CycleInvestment::query()->create([
        'fund_cycle_id' => $business->fund_cycle_id, 'type' => CycleInvestment::TYPE_EVENT, 'title' => 'Fair',
    ]);
    $entry = $other->subEntries()->create([
        'direction' => 'expense', 'category' => 'খাবার', 'amount' => 100, 'entry_date' => '2026-04-01',
    ]);

    actingAs($admin)
        ->delete(route('admin.businesses.sub-entries.destroy', [$business, $entry]))
        ->assertNotFound();

    actingAs($admin)
        ->post(route('admin.businesses.sub-entries.store', $event), [
            'direction' => 'expense', 'category' => 'খাবার', 'amount' => 100, 'entry_date' => '2026-05-01',
        ])
        ->assertNotFound();

    expect(BusinessSubEntry::query()->count())->toBe(1);
});

test('sub entry input is validated', function () {
    [$admin, $business] = subLedgerBusiness();

    actingAs($admin)
        ->post(route('admin.businesses.sub-entries.store', $business), [
            'direction' => 'gift', 'category' => '', 'amount' => 0,
        ])
        ->assertSessionHasErrors(['direction', 'category', 'amount', 'entry_date']);
});
