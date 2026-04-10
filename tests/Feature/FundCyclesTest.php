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
use function Pest\Laravel\post;
use function Pest\Laravel\put;

test('admins can visit the fund cycle admin page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 4000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'FC-INDEX',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/fc-index.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    FundCycle::query()->create([
        'name' => 'April 2026 Cycle',
        'status' => FundCycle::STATUS_DRAFT,
        'start_date' => '2026-04-01',
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.fund-cycles.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/FundCycles')
            ->has('fundCycles', 1)
            ->where('statuses.0', FundCycle::STATUS_DRAFT)
            ->where('poolSummary.total_verified_deposits', 4000));
});

test('members cannot visit the fund cycle admin page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($user)
        ->get(route('admin.fund-cycles.index'))
        ->assertForbidden();
});

test('admins can create a fund cycle', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin);

    post(route('admin.fund-cycles.store'), [
        'name' => 'May 2026 Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'start_date' => '2026-05-01',
        'lock_date' => '2026-05-10',
        'maturity_date' => '2026-06-10',
        'settlement_date' => '2026-06-15',
        'notes' => 'Initial cycle window',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    $fundCycle = FundCycle::query()->where('name', 'May 2026 Cycle')->first();

    expect($fundCycle)->not->toBeNull()
        ->and($fundCycle?->created_by_user_id)->toBe($admin->id);
});

test('admins can update a fund cycle', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'June 2026 Cycle',
        'status' => FundCycle::STATUS_DRAFT,
        'start_date' => '2026-06-01',
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    put(route('admin.fund-cycles.update', $fundCycle), [
        'name' => 'June 2026 Locked Cycle',
        'status' => FundCycle::STATUS_LOCKED,
        'start_date' => '2026-06-01',
        'lock_date' => '2026-06-10',
        'maturity_date' => '2026-07-10',
        'settlement_date' => '2026-07-15',
        'notes' => 'Ready for investment',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    expect($fundCycle->refresh()->name)->toBe('June 2026 Locked Cycle')
        ->and($fundCycle->status)->toBe(FundCycle::STATUS_LOCKED)
        ->and($fundCycle->notes)->toBe('Ready for investment');
});

test('admins can allocate verified deposit pool into a fund cycle for an approved member', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Allocation Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'start_date' => '2026-04-01',
        'created_by_user_id' => $admin->id,
    ]);
    $member = Member::factory()->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);

    DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 5000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'FC-ALLOC-01',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/fc-alloc.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.fund-cycles.allocations.store', $fundCycle), [
        'member_id' => $member->id,
        'amount' => 2000,
        'notes' => 'Initial member allocation',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    $allocation = FundCycleAllocation::query()->where('fund_cycle_id', $fundCycle->id)->first();

    expect($allocation)->not->toBeNull()
        ->and($allocation?->member_id)->toBe($member->id)
        ->and($allocation?->amount)->toBe(2000);
});

test('fund cycle allocation cannot exceed the remaining verified deposit pool', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Limited Pool Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'start_date' => '2026-04-01',
        'created_by_user_id' => $admin->id,
    ]);
    $member = Member::factory()->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);
    $chargeMember = Member::factory()->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();
    $charge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $chargeMember->id,
        'amount' => 500,
        'status' => Charge::STATUS_POSTED,
        'effective_at' => now(),
    ]);

    DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 2500,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'FC-ALLOC-LIMIT',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/fc-alloc-limit.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    ChargeAllocation::query()->create([
        'charge_id' => $charge->id,
        'amount' => 500,
        'confirmed_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.fund-cycles.allocations.store', $fundCycle), [
        'member_id' => $member->id,
        'amount' => 2500,
        'notes' => 'Too large for remaining pool',
    ])->assertSessionHasErrors(['amount']);

    expect(FundCycleAllocation::query()->where('fund_cycle_id', $fundCycle->id)->exists())->toBeFalse();
});
