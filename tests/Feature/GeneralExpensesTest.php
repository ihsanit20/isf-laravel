<?php

use App\Enums\DepositSubmissionStatus;
use App\Enums\GeneralExpenseCategory;
use App\Enums\GeneralIncomeCategory;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Postings\PlatformPostings;
use App\Models\DepositSubmission;
use App\Models\GeneralExpense;
use App\Models\GeneralIncome;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function fundPlatform(int $amount): void
{
    app(PlatformPostings::class)->incomeRecorded(GeneralIncome::query()->create([
        'income_date' => '2026-04-01',
        'category' => GeneralIncomeCategory::Sponsorship,
        'amount' => $amount,
    ]));
}

test('admins can visit the general expenses admin page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin)
        ->get(route('admin.general-expenses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
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
    fundPlatform(5000);

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
    fundPlatform(5000);
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

test('platform expenses may run the platform fund negative but not the bank', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    fundPlatform(1000);

    actingAs($admin);

    post(route('admin.general-expenses.store'), [
        'expense_date' => '2026-04-13',
        'category' => GeneralExpenseCategory::SmsCharge->value,
        'amount' => 400,
    ])->assertSessionHasNoErrors();

    expect(app(Ledger::class)->balance(Account::SmsChargeExpense))->toBe(40000)
        ->and(app(Ledger::class)->platformFund())->toBe(60000);

    post(route('admin.general-expenses.store'), [
        'expense_date' => '2026-04-14',
        'category' => GeneralExpenseCategory::BankCharge->value,
        'amount' => 700,
    ])->assertSessionHasErrors(['amount']);

    expect(GeneralExpense::query()->count())->toBe(1);

    $member = User::factory()->create();
    DepositSubmission::query()->create([
        'user_id' => $member->id,
        'amount' => 5000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'deposit_date' => '2026-04-14',
        'proof_path' => 'proofs/x.png',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    post(route('admin.general-expenses.store'), [
        'expense_date' => '2026-04-15',
        'category' => GeneralExpenseCategory::BankCharge->value,
        'amount' => 700,
    ])->assertSessionHasNoErrors();

    expect(app(Ledger::class)->platformFund())->toBe(-10000)
        ->and(app(Ledger::class)->creditBalance(Account::MemberBalance, ['user_id' => $member->id]))->toBe(500000);
});
