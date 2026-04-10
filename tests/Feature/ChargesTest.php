<?php

use App\Models\Charge;
use App\Models\ChargeCategory;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

test('admins can visit the charge review page', function () {
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

test('members cannot visit the charge review page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($user)
        ->get(route('admin.charges.index'))
        ->assertForbidden();
});

test('admins can post a pending charge', function () {
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

    patch(route('admin.charges.review', $charge), [
        'status' => Charge::STATUS_POSTED,
    ])->assertRedirect(route('admin.charges.index'));

    expect($charge->refresh()->status)->toBe(Charge::STATUS_POSTED)
        ->and($charge->settled_by_user_id)->toBe($admin->id)
        ->and($charge->settled_at)->not->toBeNull();
});

test('admins can waive or cancel a pending charge without settlement markers', function (string $status) {
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

    patch(route('admin.charges.review', $charge), [
        'status' => $status,
    ])->assertRedirect(route('admin.charges.index'));

    expect($charge->refresh()->status)->toBe($status)
        ->and($charge->settled_by_user_id)->toBeNull()
        ->and($charge->settled_at)->toBeNull();
})->with([
    Charge::STATUS_WAIVED,
    Charge::STATUS_CANCELLED,
]);

test('reviewing a non-pending charge is rejected', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create();
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();
    $charge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 100,
        'status' => Charge::STATUS_POSTED,
        'effective_at' => now(),
        'settled_at' => now(),
        'settled_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    patch(route('admin.charges.review', $charge), [
        'status' => Charge::STATUS_CANCELLED,
    ])->assertSessionHasErrors('status');

    expect($charge->refresh()->status)->toBe(Charge::STATUS_POSTED);
});
