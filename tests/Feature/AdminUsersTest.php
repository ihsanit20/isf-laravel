<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

test('guests are redirected to the login page from admin user list', function () {
    get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

test('admins can visit the admin user list page', function (string $role) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $listedUsers = User::factory()->count(2)->create();

    actingAs($user)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('admin/Users')
                ->has('users', $listedUsers->count() + 1)
                ->where('assignableRoles', User::assignableRolesFor($role)),
        );
})->with(['admin', 'super_admin']);

test('members cannot visit the admin user list page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admins can add a user with an allowed role', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin);

    post(route('admin.users.store'), [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'role' => 'admin',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('admin.users.index'));

    $createdUser = User::query()->where('email', 'new-admin@example.com')->first();

    expect($createdUser)->not->toBeNull();
    expect($createdUser?->role)->toBe('admin');
});

test('admins cannot assign a higher role than their own when creating a user', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin);

    post(route('admin.users.store'), [
        'name' => 'Blocked User',
        'email' => 'blocked@example.com',
        'role' => 'super_admin',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'blocked@example.com')->exists())->toBeFalse();
});

test('admins can edit a user from the list', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $member = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($admin);

    put(route('admin.users.update', $member), [
        'name' => 'Updated Member',
        'email' => 'updated-member@example.com',
        'role' => 'admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('admin.users.index'));

    expect($member->refresh()->name)->toBe('Updated Member');
    expect($member->email)->toBe('updated-member@example.com');
    expect($member->role)->toBe('admin');
});

test('admins cannot edit a user to a higher role than their own', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $member = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($admin);

    put(route('admin.users.update', $member), [
        'name' => $member->name,
        'email' => $member->email,
        'role' => 'super_admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasErrors('role');

    expect($member->refresh()->role)->toBe('member');
});
