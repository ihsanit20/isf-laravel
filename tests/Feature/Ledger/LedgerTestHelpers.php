<?php

use App\Enums\DepositSubmissionStatus;
use App\Enums\FundCycleEventStatus;
use App\Enums\MemberStatus;
use App\Ledger\Postings\MemberPostings;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\Member;
use App\Models\User;

function ledgerAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function ledgerCycle(User $admin, array $attributes = []): FundCycle
{
    return FundCycle::query()->create([
        'name' => 'Cycle 2',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 100000,
        'start_date' => '2026-01-01',
        'slots' => ['S1'],
        'created_by_user_id' => $admin->id,
        ...$attributes,
    ]);
}

function ledgerMember(User $user, int $units): Member
{
    return Member::factory()->create([
        'managed_by_user_id' => $user->id,
        'units' => $units,
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);
}

function ledgerVerifiedDeposit(User $user, int $amount): DepositSubmission
{
    $deposit = DepositSubmission::query()->create([
        'user_id' => $user->id,
        'amount' => $amount,
        'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
        'deposit_date' => '2026-01-01',
        'proof_path' => 'proofs/x.png',
        'status' => DepositSubmissionStatus::Verified,
        'verified_at' => now(),
    ]);

    app(MemberPostings::class)->depositVerified($deposit);

    return $deposit;
}

function ledgerEvent(FundCycle $cycle, string $title = 'Mango Fair'): FundCycleEvent
{
    return $cycle->events()->create([
        'title' => $title,
        'slug' => str($title)->slug().'-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => '2026-01-01 00:00:00',
        'order_close_at' => '2026-12-31 00:00:00',
    ]);
}

function fundedCycle(int $capital = 100000, int $extraDeposit = 0): array
{
    $admin = ledgerAdmin();
    $user = User::factory()->create();
    $cycle = ledgerCycle($admin);
    $member = ledgerMember($user, 1);
    ledgerVerifiedDeposit($user, $capital + $extraDeposit);

    app(MemberPostings::class)->allocateToCycle([
        'fund_cycle_id' => $cycle->id,
        'member_id' => $member->id,
        'slot_key' => 'S1',
        'amount' => $capital,
        'allocated_at' => now(),
        'created_by_user_id' => $admin->id,
    ], $user->id);

    return compact('admin', 'cycle', 'user', 'member');
}
