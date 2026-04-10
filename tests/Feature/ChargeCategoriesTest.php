<?php

use App\Models\ChargeCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

test('admins can visit the charge category admin page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin)
        ->get(route('admin.charge-categories.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/ChargeCategories')
            ->has('chargeCategories', 1));
});

test('members cannot visit the charge category admin page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($user)
        ->get(route('admin.charge-categories.index'))
        ->assertForbidden();
});

test('admins can create a charge category', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin);

    post(route('admin.charge-categories.store'), [
        'code' => 'late_fee',
        'title' => 'Late Fee',
        'default_amount' => 50,
        'is_active' => true,
    ])->assertRedirect(route('admin.charge-categories.index'));

    expect(ChargeCategory::query()->where('code', 'late_fee')->exists())->toBeTrue();
});

test('admins can update a non-system charge category', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $category = ChargeCategory::query()->create([
        'code' => 'manual_adjustment',
        'title' => 'Manual Adjustment',
        'default_amount' => 20,
        'is_active' => true,
    ]);

    actingAs($admin);

    put(route('admin.charge-categories.update', $category), [
        'code' => 'manual_adjustment',
        'title' => 'Admin Adjustment',
        'default_amount' => 25,
        'is_active' => false,
    ])->assertRedirect(route('admin.charge-categories.index'));

    expect($category->refresh()->title)->toBe('Admin Adjustment')
        ->and($category->default_amount)->toBe(25)
        ->and($category->is_active)->toBeFalse();
});

test('admins cannot change the registration fee category code or deactivate it', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();

    actingAs($admin);

    put(route('admin.charge-categories.update', $category), [
        'code' => 'new_registration_fee',
        'title' => 'Updated Registration Fee',
        'default_amount' => 120,
        'is_active' => false,
    ])->assertSessionHasErrors(['code', 'is_active']);

    expect($category->refresh()->code)->toBe(ChargeCategory::CODE_REGISTRATION_FEE)
        ->and($category->is_active)->toBeTrue();
});
