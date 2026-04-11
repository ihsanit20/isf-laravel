<?php

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected to the login page', function () {
    $response = get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated members can visit the dashboard with personal data', function () {
    $user = User::factory()->create();

    $member = Member::factory()->for($user, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
        'units' => 2,
    ]);

    DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 6000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'DB-6000',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/dashboard-member.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    $fundCycle = FundCycle::query()->create([
        'name' => 'April 2026 Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->startOfMonth()->toDateString(),
        'lock_date' => now()->addDays(5)->toDateString(),
        'slots' => ['April 2026'],
        'created_by_user_id' => User::factory()->create(['role' => 'admin'])->id,
    ]);

    FundCycleAllocation::query()->create([
        'fund_cycle_id' => $fundCycle->id,
        'member_id' => $member->id,
        'slot_key' => 'April 2026',
        'amount' => 2000,
        'allocated_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    actingAs($user);

    $response = get(route('dashboard'));
    $response->assertOk()->assertInertia(fn(Assert $page) => $page
        ->component('Dashboard')
        ->where('personal.summary.total_members', 1)
        ->where('personal.summary.active_members', 1)
        ->where('personal.summary.verified_deposits', 6000)
        ->where('personal.summary.available_balance', 4000)
        ->where('personal.actions.my_cycle_allocation_count', 1)
        ->where('adminOverview', null));
});

test('admins see both personal and admin dashboard data', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $managedMember = Member::factory()->for($admin, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => null,
        'units' => 1,
    ]);

    $registrationCategory = ChargeCategory::query()
        ->where('code', ChargeCategory::CODE_REGISTRATION_FEE)
        ->firstOrFail();

    $charge = Charge::query()->create([
        'charge_category_id' => $registrationCategory->id,
        'member_id' => $managedMember->id,
        'amount' => 100,
        'status' => Charge::STATUS_PENDING,
        'effective_at' => now(),
    ]);

    DepositSubmission::query()->create([
        'user_id' => $admin->id,
        'amount' => 5000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'ADMIN-DB-01',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/dashboard-admin.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 3000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'ADMIN-PENDING-01',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/dashboard-admin-pending.jpg',
        'status' => DepositSubmissionStatus::Pending,
    ]);

    $otherApproved = Member::factory()->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => null,
    ]);

    ChargeAllocation::query()->create([
        'charge_id' => $charge->id,
        'amount' => 100,
        'confirmed_at' => now(),
        'reversed_at' => now(),
    ]);

    FundCycle::query()->create([
        'name' => 'Admin Open Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->startOfMonth()->toDateString(),
        'slots' => ['April 2026'],
        'created_by_user_id' => $admin->id,
    ]);

    FundCycle::query()->create([
        'name' => 'Admin Draft Cycle',
        'status' => FundCycle::STATUS_DRAFT,
        'unit_amount' => 1000,
        'start_date' => now()->startOfMonth()->toDateString(),
        'slots' => ['May 2026'],
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->where('personal.summary.total_members', 1)
            ->where('personal.actions.my_charge_count', 1)
            ->where('personal.actions.pending_charge_count', 1)
            ->where('adminOverview.pool_summary.total_verified_deposits', 5000)
            ->where('adminOverview.queues.pending_deposits', 1)
            ->where('adminOverview.queues.approved_not_activated_members', 2)
            ->where('adminOverview.queues.pending_charges', 1)
            ->where('adminOverview.cycle_statuses.0.status', FundCycle::STATUS_DRAFT));
});
