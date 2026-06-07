<?php

use App\Enums\GeneralIncomeCategory;
use App\Models\GeneralIncome;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('admins can visit the general incomes admin page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin)
        ->get(route('admin.general-incomes.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/GeneralIncomes')
            ->has('incomeCategories', count(GeneralIncomeCategory::cases()))
            ->has('generalIncomes', 0));
});

test('members cannot visit the general incomes admin page', function () {
    $member = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($member)
        ->get(route('admin.general-incomes.index'))
        ->assertForbidden();
});

test('admins can create a general income', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin);

    post(route('admin.general-incomes.store'), [
        'income_date' => '2026-06-07',
        'category' => GeneralIncomeCategory::Donation->value,
        'amount' => 5000,
        'description' => 'Annual fund donation',
        'receipt' => UploadedFile::fake()->create('donation-receipt.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('admin.general-incomes.index'));

    $income = GeneralIncome::query()->firstOrFail();

    expect($income->category)->toBe(GeneralIncomeCategory::Donation)
        ->and($income->amount)->toBe(5000)
        ->and($income->created_by_user_id)->toBe($admin->id)
        ->and($income->receipt_path)->not->toBeNull();

    expect(Storage::disk('public')->exists($income->receipt_path))->toBeTrue();
});

test('admins can update a general income', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $income = GeneralIncome::query()->create([
        'income_date' => '2026-06-01',
        'category' => GeneralIncomeCategory::BankInterest,
        'amount' => 1500,
        'description' => 'Old bank interest entry',
        'receipt_path' => null,
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    post(route('admin.general-incomes.update', $income), [
        '_method' => 'PUT',
        'income_date' => '2026-06-07',
        'category' => GeneralIncomeCategory::Sponsorship->value,
        'amount' => 10000,
        'description' => 'Updated sponsorship income',
        'receipt' => UploadedFile::fake()->create('sponsorship.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('admin.general-incomes.index'));

    expect($income->refresh()->category)->toBe(GeneralIncomeCategory::Sponsorship)
        ->and($income->amount)->toBe(10000)
        ->and($income->description)->toBe('Updated sponsorship income')
        ->and($income->receipt_path)->not->toBeNull();

    expect(Storage::disk('public')->exists($income->receipt_path))->toBeTrue();
});
