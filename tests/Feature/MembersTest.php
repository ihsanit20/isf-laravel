<?php

use App\Enums\MemberStatus;
use App\Models\Charge;
use App\Models\ChargeCategory;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

test('authenticated users can view their member list page', function () {
    $user = User::factory()->create();
    Member::factory()->for($user, 'manager')->count(2)->create();

    actingAs($user)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Members')
            ->has('members', 2));
});

test('authenticated users can view the membership form page', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('members.create'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('members/Create')
            ->where('relationshipOptions', Member::relationshipOptions()));
});

test('user can submit multiple member applications', function () {
    $user = User::factory()->create();

    actingAs($user);

    post(route('members.store'), [
        'full_name' => 'Applicant One',
        'phone' => '01711111111',
        'relationship_to_user' => 'self',
        'units' => 1,
    ])->assertRedirect(route('members.index'));

    post(route('members.store'), [
        'full_name' => 'Applicant Two',
        'phone' => '01722222222',
        'relationship_to_user' => 'spouse',
        'units' => 3,
    ])->assertRedirect(route('members.index'));

    expect($user->managedMembers()->count())->toBe(2);
    expect($user->managedMembers()->where('full_name', 'Applicant Two')->first()?->units)->toBe(3);
    expect($user->managedMembers()->where('full_name', 'Applicant One')->first()?->status)
        ->toBe(MemberStatus::Pending);
});

test('user only sees members they manage', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownedMember = Member::factory()->for($user, 'manager')->create([
        'full_name' => 'Owned Member',
    ]);
    Member::factory()->for($otherUser, 'manager')->create([
        'full_name' => 'Other Member',
    ]);

    actingAs($user)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Members')
            ->has('members', 1)
            ->where('members.0.full_name', $ownedMember->full_name));
});

test('member application validates units and relationship', function () {
    $user = User::factory()->create();

    actingAs($user);

    post(route('members.store'), [
        'full_name' => 'Invalid Member',
        'phone' => '01733333333',
        'relationship_to_user' => 'cousin',
        'units' => 0,
    ])->assertSessionHasErrors([
        'relationship_to_user',
        'units',
    ]);
});

test('admins can view the admin member list page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Member::factory()->count(2)->create();

    actingAs($admin)
        ->get(route('admin.members.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/Members')
            ->has('members', 2));
});

test('members cannot view the admin member list page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($user)
        ->get(route('admin.members.index'))
        ->assertForbidden();
});

test('admins can approve a member application', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create();

    actingAs($admin);

    patch(route('admin.members.review', $member), [
        'status' => MemberStatus::Approved->value,
        'rejection_note' => '',
    ])->assertRedirect(route('admin.members.index'));

    expect($member->refresh()->status)->toBe(MemberStatus::Approved);
    expect($member->approved_by_user_id)->toBe($admin->id);
    expect($member->approved_at)->not->toBeNull();
    expect($member->activated_at)->toBeNull();
    expect($member->charges()->count())->toBe(1);

    $registrationCharge = $member->charges()->first();
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->first();

    expect($registrationCharge?->charge_category_id)->toBe($category?->id)
        ->and($registrationCharge?->amount)->toBe($category?->default_amount)
        ->and($registrationCharge?->status)->toBe(Charge::STATUS_PENDING);
});

test('approving an already approved member does not duplicate registration fee charges', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create();

    actingAs($admin);

    patch(route('admin.members.review', $member), [
        'status' => MemberStatus::Approved->value,
        'rejection_note' => '',
    ])->assertRedirect(route('admin.members.index'));

    patch(route('admin.members.review', $member->refresh()), [
        'status' => MemberStatus::Approved->value,
        'rejection_note' => '',
    ])->assertRedirect(route('admin.members.index'));

    expect($member->refresh()->charges()->count())->toBe(1);
});

test('admins can reject a member application with note', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create([
        'status' => MemberStatus::Pending,
    ]);

    actingAs($admin);

    patch(route('admin.members.review', $member), [
        'status' => MemberStatus::Rejected->value,
        'rejection_note' => 'Phone number could not be verified.',
    ])->assertRedirect(route('admin.members.index'));

    expect($member->refresh()->status)->toBe(MemberStatus::Rejected);
    expect($member->rejection_note)->toBe('Phone number could not be verified.');
    expect($member->approved_by_user_id)->toBeNull();
    expect($member->approved_at)->toBeNull();
});

test('rejecting a member requires a rejection note', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create();

    actingAs($admin);

    patch(route('admin.members.review', $member), [
        'status' => MemberStatus::Rejected->value,
        'rejection_note' => '',
    ])->assertSessionHasErrors('rejection_note');
});

test('admins can mark an approved member as exited', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $member = Member::factory()->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now()->subDay(),
        'approved_by_user_id' => $admin->id,
    ]);

    $approvedAt = $member->approved_at;

    actingAs($admin);

    patch(route('admin.members.review', $member), [
        'status' => MemberStatus::Exited->value,
        'rejection_note' => '',
    ])->assertRedirect(route('admin.members.index'));

    expect($member->refresh()->status)->toBe(MemberStatus::Exited);
    expect($member->approved_by_user_id)->toBe($admin->id);
    expect($member->approved_at?->toDateTimeString())->toBe($approvedAt?->toDateTimeString());
    expect($member->rejection_note)->toBeNull();
});
