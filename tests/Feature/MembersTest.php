<?php

use App\Enums\MemberStatus;
use App\Enums\DepositSubmissionStatus;
use App\Models\DepositAllocation;
use App\Models\DepositSubmission;
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

test('users can view the annual unit calendar for their managed member', function () {
    $user = User::factory()->create();

    $member = Member::factory()->for($user, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now()->subMonths(3),
    ]);

    DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 4000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'CAL-2026-01',
        'deposit_date' => '2026-01-15',
        'proof_path' => 'deposit-proofs/calendar-proof.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    DepositAllocation::query()->create([
        'member_id' => $member->id,
        'allocation_month' => '2026-01-01',
        'units' => 2,
        'unit_amount' => 1000,
        'allocated_amount' => 2000,
        'confirmed_at' => now(),
    ]);

    actingAs($user)
        ->get(route('members.unit-calendar', $member))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('members/UnitCalendar')
            ->where('member.id', $member->id)
            ->where('selectedYear', 2026)
            ->where('yearlySummary.total_units', 2)
            ->where('yearlySummary.total_amount', 2000)
            ->has('months', 12)
            ->where('months.0.is_paid', true)
            ->where('months.0.total_units', 2));
});

test('users cannot view another users member unit calendar', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $member = Member::factory()->for($otherUser, 'manager')->create();

    actingAs($user)
        ->get(route('members.unit-calendar', $member))
        ->assertForbidden();
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

test('admins can view a members annual unit calendar', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $user = User::factory()->create();

    $member = Member::factory()->for($user, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now()->subMonths(4),
    ]);

    DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 5000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'ADMIN-CAL-01',
        'deposit_date' => '2025-12-20',
        'proof_path' => 'deposit-proofs/admin-calendar-proof.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    DepositAllocation::query()->create([
        'member_id' => $member->id,
        'allocation_month' => '2025-12-01',
        'units' => 1,
        'unit_amount' => 1000,
        'allocated_amount' => 1000,
        'confirmed_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.members.unit-calendar', ['member' => $member, 'year' => 2025]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/members/UnitCalendar')
            ->where('member.id', $member->id)
            ->where('selectedYear', 2025)
            ->where('yearlySummary.total_units', 1)
            ->where('yearlySummary.total_amount', 1000)
            ->where('months.11.is_paid', true));
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
