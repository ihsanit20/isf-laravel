<?php

use App\Enums\MemberStatus;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('authenticated users can view their allocation list', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $memberOne = Member::factory()->for($user, 'manager')->create([
        'full_name' => 'Primary Member',
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
        'units' => 1,
    ]);
    $memberTwo = Member::factory()->for($user, 'manager')->create([
        'full_name' => 'Family Member',
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
        'units' => 2,
    ]);
    $otherMember = Member::factory()->for($otherUser, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);

    $cycleOne = FundCycle::query()->create([
        'name' => 'April 2026 Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->startOfMonth()->toDateString(),
        'slots' => ['April 2026'],
        'created_by_user_id' => User::factory()->create(['role' => 'admin'])->id,
    ]);
    $cycleTwo = FundCycle::query()->create([
        'name' => 'May 2026 Cycle',
        'status' => FundCycle::STATUS_LOCKED,
        'unit_amount' => 1200,
        'start_date' => now()->startOfMonth()->toDateString(),
        'slots' => ['May 2026'],
        'created_by_user_id' => User::factory()->create(['role' => 'admin'])->id,
    ]);

    FundCycleAllocation::query()->create([
        'fund_cycle_id' => $cycleOne->id,
        'member_id' => $memberOne->id,
        'slot_key' => 'April 2026',
        'amount' => 1000,
        'allocated_at' => now()->subDay(),
        'notes' => 'Primary slot',
        'created_by_user_id' => $user->id,
    ]);

    FundCycleAllocation::query()->create([
        'fund_cycle_id' => $cycleTwo->id,
        'member_id' => $memberTwo->id,
        'slot_key' => 'May 2026',
        'amount' => 2400,
        'allocated_at' => now(),
        'notes' => 'Family allocation',
        'created_by_user_id' => $user->id,
    ]);

    FundCycleAllocation::query()->create([
        'fund_cycle_id' => $cycleOne->id,
        'member_id' => $otherMember->id,
        'slot_key' => 'April 2026',
        'amount' => 1000,
        'allocated_at' => now(),
        'created_by_user_id' => $otherUser->id,
    ]);

    actingAs($user);

    get(route('allocations.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Allocations')
            ->where('summary.total_allocations', 2)
            ->where('summary.total_allocated_amount', 3400)
            ->where('summary.member_count', 2)
            ->where('summary.cycle_count', 2)
            ->has('allocations', 2)
            ->where('allocations.0.member_name', 'Family Member')
            ->where('allocations.0.cycle_name', 'May 2026 Cycle')
            ->where('allocations.0.slot_key', 'May 2026')
            ->where('allocations.0.amount', 2400));
});

test('guests are redirected from my allocations', function () {
    get(route('allocations.index'))
        ->assertRedirect(route('login'));
});
