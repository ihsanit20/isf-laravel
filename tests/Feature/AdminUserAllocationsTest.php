<?php

use App\Enums\MemberStatus;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected from admin user allocations', function () {
    $user = User::factory()->create();

    get(route('admin.users.allocations', $user))
        ->assertRedirect(route('login'));
});

test('members cannot view admin user allocations', function () {
    $member = User::factory()->create(['role' => 'member']);
    $user = User::factory()->create();

    actingAs($member)
        ->get(route('admin.users.allocations', $user))
        ->assertForbidden();
});

test('admins can view allocation history of any user', function (string $role) {
    $admin = User::factory()->create(['role' => $role]);
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $member = Member::factory()->for($user, 'manager')->create([
        'full_name' => 'Listed Member',
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
        'units' => 1,
    ]);
    $otherMember = Member::factory()->for($otherUser, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);

    $cycle = FundCycle::query()->create([
        'name' => 'April 2026 Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->startOfMonth()->toDateString(),
        'slots' => ['April 2026', 'May 2026'],
        'created_by_user_id' => $admin->id,
    ]);

    FundCycleAllocation::query()->create([
        'fund_cycle_id' => $cycle->id,
        'member_id' => $member->id,
        'slot_key' => 'April 2026',
        'amount' => 1000,
        'allocated_at' => now(),
    ]);
    FundCycleAllocation::query()->create([
        'fund_cycle_id' => $cycle->id,
        'member_id' => $otherMember->id,
        'slot_key' => 'April 2026',
        'amount' => 1000,
        'allocated_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.users.allocations', $user))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Allocations')
                ->where('readOnly', true)
                ->where('viewingUser.id', $user->id)
                ->where('summary.total_allocations', 1)
                ->has('memberTabs', 1)
                ->where('memberTabs.0.member.id', $member->id)
                ->where('summary.due_count', 1)
                ->where('summary.due_amount', 1000)
                ->has('memberTabs.0.rows', 2)
                ->where('memberTabs.0.rows.0.status', 'allocated')
                ->where('memberTabs.0.rows.1.status', 'unallocated'),
        );
})->with(['admin', 'super_admin']);
