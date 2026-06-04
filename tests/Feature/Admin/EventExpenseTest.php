<?php

use App\Enums\EventExpenseCategory;
use App\Enums\FundCycleEventStatus;
use App\Models\EventExpense;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;

/**
 * @return array{event: FundCycleEvent, otherEvent: FundCycleEvent}
 */
function createEventsForExpenseTests(): array
{
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Expense Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->subMonth()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Expense Event',
        'slug' => 'expense-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $otherEvent = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Other Event',
        'slug' => 'other-event-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    return ['event' => $event, 'otherEvent' => $otherEvent];
}

test('admins see event expenses on the event details page', function () {
    ['event' => $event] = createEventsForExpenseTests();

    $admin = User::factory()->create(['role' => 'admin']);

    $expense = $event->expenses()->create([
        'expense_date' => '2026-06-01',
        'category' => EventExpenseCategory::Procurement,
        'amount' => 5000,
        'description' => 'Initial stock purchase',
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/EventDetails')
            ->has('expenseCategories', count(EventExpenseCategory::cases()))
            ->has('event.expenses', 1)
            ->where('event.expenses.0.id', $expense->id)
            ->where('event.expenses.0.category_label', EventExpenseCategory::Procurement->label())
            ->where('event.expense_summary.total_amount', 5000)
            ->where('event.expense_summary.entry_count', 1));
});

test('members cannot view event details with expenses', function () {
    ['event' => $event] = createEventsForExpenseTests();

    $member = User::factory()->create(['role' => 'member']);

    actingAs($member)
        ->get(route('admin.events.show', $event))
        ->assertForbidden();
});

test('admins can create an event expense', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    ['event' => $event] = createEventsForExpenseTests();
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin);

    post(route('admin.events.expenses.store', $event), [
        'expense_date' => '2026-06-02',
        'category' => EventExpenseCategory::Transport->value,
        'amount' => 1200,
        'description' => 'Delivery van fuel',
        'receipt' => UploadedFile::fake()->create('fuel.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('admin.events.show', [
        'fundCycleEvent' => $event,
        'tab' => 'costs',
    ]));

    $expense = EventExpense::query()->firstOrFail();

    expect($expense->fund_cycle_event_id)->toBe($event->id)
        ->and($expense->category)->toBe(EventExpenseCategory::Transport)
        ->and($expense->amount)->toBe(1200)
        ->and($expense->created_by_user_id)->toBe($admin->id)
        ->and($expense->receipt_path)->not->toBeNull();

    expect(Storage::disk('public')->exists($expense->receipt_path))->toBeTrue();
});

test('admins cannot create an event expense with invalid data', function () {
    ['event' => $event] = createEventsForExpenseTests();
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin);

    post(route('admin.events.expenses.store', $event), [
        'expense_date' => '',
        'category' => 'invalid',
        'amount' => 0,
    ])->assertSessionHasErrors(['expense_date', 'category', 'amount']);

    expect(EventExpense::query()->count())->toBe(0);
});

test('admins can update an event expense', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    ['event' => $event] = createEventsForExpenseTests();
    $admin = User::factory()->create(['role' => 'admin']);

    $expense = $event->expenses()->create([
        'expense_date' => '2026-06-01',
        'category' => EventExpenseCategory::Marketing,
        'amount' => 800,
        'description' => 'Old banner cost',
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    post(route('admin.events.expenses.update', [$event, $expense]), [
        '_method' => 'PUT',
        'expense_date' => '2026-06-03',
        'category' => EventExpenseCategory::Packaging->value,
        'amount' => 950,
        'description' => 'Updated packaging supplies',
    ])->assertRedirect(route('admin.events.show', [
        'fundCycleEvent' => $event,
        'tab' => 'costs',
    ]));

    expect($expense->refresh()->category)->toBe(EventExpenseCategory::Packaging)
        ->and($expense->amount)->toBe(950)
        ->and($expense->description)->toBe('Updated packaging supplies');
});

test('admins cannot update an expense from another event', function () {
    ['event' => $event, 'otherEvent' => $otherEvent] = createEventsForExpenseTests();
    $admin = User::factory()->create(['role' => 'admin']);

    $expense = $otherEvent->expenses()->create([
        'expense_date' => '2026-06-01',
        'category' => EventExpenseCategory::Other,
        'amount' => 100,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    post(route('admin.events.expenses.update', [$event, $expense]), [
        '_method' => 'PUT',
        'expense_date' => '2026-06-04',
        'category' => EventExpenseCategory::Labor->value,
        'amount' => 200,
        'description' => 'Should not apply',
    ])->assertNotFound();

    expect($expense->refresh()->amount)->toBe(100);
});

test('admins can delete an event expense', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    ['event' => $event] = createEventsForExpenseTests();
    $admin = User::factory()->create(['role' => 'admin']);

    $expense = $event->expenses()->create([
        'expense_date' => '2026-06-01',
        'category' => EventExpenseCategory::HubOps,
        'amount' => 300,
        'receipt_path' => 'event-expense-attachments/'.$event->id.'/receipt.pdf',
        'created_by_user_id' => $admin->id,
    ]);

    Storage::disk('public')->put($expense->receipt_path, 'fake');

    actingAs($admin);

    delete(route('admin.events.expenses.destroy', [$event, $expense]))
        ->assertRedirect(route('admin.events.show', [
            'fundCycleEvent' => $event,
            'tab' => 'costs',
        ]));

    expect(EventExpense::query()->find($expense->id))->toBeNull();
    expect(Storage::disk('public')->exists($expense->receipt_path))->toBeFalse();
});

test('admins cannot delete an expense from another event', function () {
    ['event' => $event, 'otherEvent' => $otherEvent] = createEventsForExpenseTests();
    $admin = User::factory()->create(['role' => 'admin']);

    $expense = $otherEvent->expenses()->create([
        'expense_date' => '2026-06-01',
        'category' => EventExpenseCategory::PaymentFee,
        'amount' => 50,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    delete(route('admin.events.expenses.destroy', [$event, $expense]))
        ->assertNotFound();

    expect(EventExpense::query()->find($expense->id))->not->toBeNull();
});

test('deleting an event cascades to its expenses', function () {
    ['event' => $event] = createEventsForExpenseTests();

    $event->expenses()->create([
        'expense_date' => '2026-06-01',
        'category' => EventExpenseCategory::Other,
        'amount' => 100,
    ]);

    expect(EventExpense::query()->count())->toBe(1);

    $event->delete();

    expect(EventExpense::query()->count())->toBe(0);
});
