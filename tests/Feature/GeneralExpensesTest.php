<?php

use App\Enums\GeneralExpenseCategory;
use App\Models\GeneralExpense;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('admins can visit the general expenses admin page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin)
        ->get(route('admin.general-expenses.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/GeneralExpenses')
            ->has('expenseCategories', count(GeneralExpenseCategory::cases()))
            ->has('generalExpenses', 0));
});

test('members cannot visit the general expenses admin page', function () {
    $member = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($member)
        ->get(route('admin.general-expenses.index'))
        ->assertForbidden();
});

test('admins can create a general expense', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin);

    post(route('admin.general-expenses.store'), [
        'expense_date' => '2026-04-13',
        'category' => GeneralExpenseCategory::ItExpense->value,
        'amount' => 1200,
        'description' => 'Monthly hosting renewal',
        'receipt' => UploadedFile::fake()->create('hosting-april.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('admin.general-expenses.index'));

    $expense = GeneralExpense::query()->firstOrFail();

    expect($expense->category)->toBe(GeneralExpenseCategory::ItExpense)
        ->and($expense->amount)->toBe(1200)
        ->and($expense->created_by_user_id)->toBe($admin->id)
        ->and($expense->receipt_path)->not->toBeNull();

    expect(Storage::disk('public')->exists($expense->receipt_path))->toBeTrue();
});

test('admins can update a general expense', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $expense = GeneralExpense::query()->create([
        'expense_date' => '2026-04-12',
        'category' => GeneralExpenseCategory::Printing,
        'amount' => 300,
        'description' => 'Old print cost',
        'receipt_path' => null,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    post(route('admin.general-expenses.update', $expense), [
        '_method' => 'PUT',
        'expense_date' => '2026-04-13',
        'category' => GeneralExpenseCategory::OfficeSupplies->value,
        'amount' => 450,
        'description' => 'Updated office supply purchase',
        'receipt' => UploadedFile::fake()->create('office-supplies.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('admin.general-expenses.index'));

    expect($expense->refresh()->category)->toBe(GeneralExpenseCategory::OfficeSupplies)
        ->and($expense->amount)->toBe(450)
        ->and($expense->description)->toBe('Updated office supply purchase')
        ->and($expense->receipt_path)->not->toBeNull();

    expect(Storage::disk('public')->exists($expense->receipt_path))->toBeTrue();
});
