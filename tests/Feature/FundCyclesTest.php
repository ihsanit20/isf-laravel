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
        'slots' => ['January 2026', 'February 2026', 'March 2026'],
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.fund-cycles.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/FundCycles')
            ->has('fundCycles', 1)
            ->where('statuses.0', FundCycle::STATUS_DRAFT)
            ->where('fundCycles.0.slots.0', 'January 2026')
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
        'slots' => ['May 2026', 'June 2026'],
        'notes' => 'Initial cycle window',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    $fundCycle = FundCycle::query()->where('name', 'May 2026 Cycle')->first();

    expect($fundCycle)->not->toBeNull()
        ->and($fundCycle?->created_by_user_id)->toBe($admin->id)
        ->and($fundCycle?->slots)->toBe(['May 2026', 'June 2026']);
});

test('admins can update a fund cycle', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'June 2026 Cycle',
        'status' => FundCycle::STATUS_DRAFT,
        'start_date' => '2026-06-01',
        'slots' => ['June 2026', 'July 2026'],
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
        'slots' => ['June 2026', 'July 2026', 'August 2026'],
        'notes' => 'Ready for investment',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    expect($fundCycle->refresh()->name)->toBe('June 2026 Locked Cycle')
        ->and($fundCycle->status)->toBe(FundCycle::STATUS_LOCKED)
        ->and($fundCycle->slots)->toBe(['June 2026', 'July 2026', 'August 2026'])
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
        'slots' => ['January 2026', 'February 2026', 'March 2026'],
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
        'slot_key' => 'January 2026',
        'amount' => 2000,
        'notes' => 'Initial member allocation',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    $allocation = FundCycleAllocation::query()->where('fund_cycle_id', $fundCycle->id)->first();

    expect($allocation)->not->toBeNull()
        ->and($allocation?->member_id)->toBe($member->id)
        ->and($allocation?->slot_key)->toBe('January 2026')
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
        'slots' => ['January 2026', 'February 2026'],
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
        'slot_key' => 'January 2026',
        'amount' => 2500,
        'notes' => 'Too large for remaining pool',
    ])->assertSessionHasErrors(['amount']);

    expect(FundCycleAllocation::query()->where('fund_cycle_id', $fundCycle->id)->exists())->toBeFalse();
});

test('the same member can be allocated in different slots of the same fund cycle', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Multi Slot Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'start_date' => '2026-04-01',
        'slots' => ['January 2026', 'February 2026'],
        'created_by_user_id' => $admin->id,
    ]);
    $member = Member::factory()->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);

    DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 6000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'FC-MULTI-SLOT',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/fc-multi-slot.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.fund-cycles.allocations.store', $fundCycle), [
        'member_id' => $member->id,
        'slot_key' => 'January 2026',
        'amount' => 1000,
        'notes' => 'First slot allocation',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    post(route('admin.fund-cycles.allocations.store', $fundCycle), [
        'member_id' => $member->id,
        'slot_key' => 'February 2026',
        'amount' => 1000,
        'notes' => 'Second slot allocation',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    expect(FundCycleAllocation::query()
        ->where('fund_cycle_id', $fundCycle->id)
        ->where('member_id', $member->id)
        ->count())->toBe(2);
});

test('approved activated member can allocate their own slot in an open fund cycle', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);
    $member = Member::factory()->create([
        'managed_by_user_id' => $user->id,
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
        'units' => 2,
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Member Self Allocation Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'start_date' => now()->startOfMonth()->toDateString(),
        'lock_date' => now()->addDays(5)->toDateString(),
        'slots' => ['April 2026', 'May 2026'],
        'created_by_user_id' => User::factory()->create(['role' => 'admin'])->id,
    ]);

    DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 5000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'SELF-ALLOC-01',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/self-alloc-01.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    actingAs($user);

    post(route('members.fund-cycles.allocations.store', [$member, $fundCycle]), [
        'slot_key' => 'April 2026',
        'notes' => 'Self allocated from member page',
    ])->assertRedirect(route('members.fund-cycles.index', $member));

    $allocation = FundCycleAllocation::query()
        ->where('fund_cycle_id', $fundCycle->id)
        ->where('member_id', $member->id)
        ->first();

    expect($allocation)->not->toBeNull()
        ->and($allocation?->slot_key)->toBe('April 2026')
        ->and($allocation?->amount)->toBe(2000);
});

test('member cannot allocate a slot after the cycle is locked', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);
    $member = Member::factory()->create([
        'managed_by_user_id' => $user->id,
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
        'units' => 1,
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Locked Member Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'start_date' => now()->startOfMonth()->toDateString(),
        'lock_date' => now()->subDay()->toDateString(),
        'slots' => ['April 2026'],
        'created_by_user_id' => User::factory()->create(['role' => 'admin'])->id,
    ]);

    DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 5000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'SELF-ALLOC-LOCKED',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/self-alloc-locked.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    actingAs($user);

    post(route('members.fund-cycles.allocations.store', [$member, $fundCycle]), [
        'slot_key' => 'April 2026',
    ])->assertSessionHasErrors(['slot_key']);

    expect(FundCycleAllocation::query()
        ->where('fund_cycle_id', $fundCycle->id)
        ->where('member_id', $member->id)
        ->exists())->toBeFalse();
});
