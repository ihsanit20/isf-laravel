<?php

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Models\DepositAllocation;
use App\Models\DepositSubmission;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

test('authenticated users can view their deposit list page', function () {
    $user = User::factory()->create();

    DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 4000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'TXN-1001',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/test-proof.jpg',
        'status' => DepositSubmissionStatus::Pending,
    ]);

    actingAs($user)
        ->get(route('deposits.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Deposits')
            ->has('deposits', 1));
});

test('authenticated users can submit a deposit proof', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    actingAs($user);

    post(route('deposits.store'), [
        'amount' => 5000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'BANK-REF-55',
        'deposit_date' => now()->toDateString(),
        'proof' => UploadedFile::fake()->image('deposit-proof.jpg'),
        'notes' => 'Combined savings deposit for family members.',
    ])->assertRedirect(route('deposits.index'));

    $depositSubmission = DepositSubmission::query()->where('user_id', $user->id)->first();

    expect($depositSubmission)->not->toBeNull();
    expect($depositSubmission?->status)->toBe(DepositSubmissionStatus::Pending);
    expect($depositSubmission?->amount)->toBe(5000);
    expect($depositSubmission?->proof_path)->not->toBeNull();

    expect(Storage::disk('public')->exists((string) $depositSubmission?->proof_path))->toBeTrue();
});

test('verified deposits can be allocated to approved managed members', function () {
    $user = User::factory()->create();

    $member = Member::factory()->for($user, 'manager')->create([
        'status' => MemberStatus::Approved,
        'units' => 2,
        'approved_at' => now(),
    ]);

    $depositSubmission = DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 6000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'ALLOC-100',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/allocation-proof.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    actingAs($user);

    post(route('deposits.allocations.store', $depositSubmission), [
        'rows' => [
            [
                'member_id' => $member->id,
                'allocation_month' => now()->format('Y-m'),
                'units' => 2,
            ],
        ],
    ])->assertRedirect(route('deposits.index'));

    $allocation = DepositAllocation::query()->where('deposit_submission_id', $depositSubmission->id)->first();

    expect($allocation)->not->toBeNull();
    expect($allocation?->member_id)->toBe($member->id);
    expect($allocation?->units)->toBe(2);
    expect($allocation?->unit_amount)->toBe(1000);
    expect($allocation?->allocated_amount)->toBe(2000);
});

test('allocation cannot exceed the remaining verified deposit amount', function () {
    $user = User::factory()->create();

    $member = Member::factory()->for($user, 'manager')->create([
        'status' => MemberStatus::Approved,
        'units' => 3,
        'approved_at' => now(),
    ]);

    $depositSubmission = DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => 1000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'ALLOC-OVER',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/allocation-proof.jpg',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    actingAs($user);

    post(route('deposits.allocations.store', $depositSubmission), [
        'rows' => [
            [
                'member_id' => $member->id,
                'allocation_month' => now()->format('Y-m'),
                'units' => 2,
            ],
        ],
    ])->assertSessionHasErrors('rows');

    expect(DepositAllocation::query()->where('deposit_submission_id', $depositSubmission->id)->exists())->toBeFalse();
});

test('admins can view the deposit review page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 3000,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'ADMIN-VIEW',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/admin-proof.jpg',
        'status' => DepositSubmissionStatus::Pending,
    ]);

    actingAs($admin)
        ->get(route('admin.deposits.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/Deposits')
            ->has('deposits', 1));
});

test('members cannot view the admin deposit review page', function () {
    $memberUser = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($memberUser)
        ->get(route('admin.deposits.index'))
        ->assertForbidden();
});

test('admins can verify a pending deposit', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $depositSubmission = DepositSubmission::query()->create([
        'user_id' => User::factory()->create()->id,
        'amount' => 4500,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'reference_no' => 'VERIFY-4500',
        'deposit_date' => now()->toDateString(),
        'proof_path' => 'deposit-proofs/verify-proof.jpg',
        'status' => DepositSubmissionStatus::Pending,
    ]);

    actingAs($admin);

    patch(route('admin.deposits.review', $depositSubmission), [
        'status' => DepositSubmissionStatus::Verified->value,
        'rejection_reason' => '',
    ])->assertRedirect(route('admin.deposits.index'));

    expect($depositSubmission->refresh()->status)->toBe(DepositSubmissionStatus::Verified);
    expect($depositSubmission->verified_by_user_id)->toBe($admin->id);
    expect($depositSubmission->verified_at)->not->toBeNull();
    expect($depositSubmission->rejection_reason)->toBeNull();
});
