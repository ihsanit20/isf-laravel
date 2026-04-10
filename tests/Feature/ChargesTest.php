<?php

use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

test('admins can visit the charge list page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create();
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();

    Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 100,
        'status' => Charge::STATUS_PENDING,
        'effective_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.charges.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/Charges')
            ->has('charges', 1));
});

test('members cannot visit the charge list page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($user)
        ->get(route('admin.charges.index'))
        ->assertForbidden();
});

test('admins can cancel a pending charge', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create();
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();
    $charge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 100,
        'status' => Charge::STATUS_PENDING,
        'effective_at' => now(),
    ]);

    actingAs($admin);

    patch(route('admin.charges.cancel', $charge))->assertRedirect(route('admin.charges.index'));

    expect($charge->refresh()->status)->toBe(Charge::STATUS_CANCELLED);
});

test('cancelling a posted charge reverses its allocation and deactivates the member when required', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create([
        'status' => \App\Enums\MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();
    $charge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 100,
        'status' => Charge::STATUS_POSTED,
        'effective_at' => now(),
    ]);
    $allocation = ChargeAllocation::query()->create([
        'charge_id' => $charge->id,
        'amount' => 100,
        'confirmed_at' => now(),
    ]);

    actingAs($admin);

    patch(route('admin.charges.cancel', $charge))->assertRedirect(route('admin.charges.index'));

    expect($charge->refresh()->status)->toBe(Charge::STATUS_CANCELLED)
        ->and($allocation->refresh()->reversed_at)->not->toBeNull()
        ->and($allocation->reversed_by_user_id)->toBe($admin->id)
        ->and($member->refresh()->activated_at)->toBeNull();
});

test('cancelling an already cancelled charge is rejected', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create();
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();
    $charge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 100,
        'status' => Charge::STATUS_CANCELLED,
        'effective_at' => now(),
    ]);

    actingAs($admin);

    patch(route('admin.charges.cancel', $charge))->assertSessionHasErrors('charge');

    expect($charge->refresh()->status)->toBe(Charge::STATUS_CANCELLED);
});
