<?php

namespace App\Ledger\Postings;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\DepositSubmission;
use App\Models\FundCycleAllocation;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MemberPostings
{
    public function __construct(private readonly Ledger $ledger) {}

    public function availableBalance(int $userId): int
    {
        return $this->ledger->creditBalance(Account::MemberBalance, ['user_id' => $userId]);
    }

    /**
     * Lock the user's pool and fail validation when spending exceeds it.
     */
    public function lockAndAssertAvailable(int $userId, int $amount, string $field, string $message): void
    {
        $this->ledger->lock('user:'.$userId);
        $this->ledger->assertAvailable(Account::MemberBalance, ['user_id' => $userId], $amount, $field, $message);
    }

    public function depositVerified(DepositSubmission $deposit, ?User $by = null): void
    {
        $amount = Money::toPaisa($deposit->amount);

        $this->ledger->entry('deposit_verified', "Deposit #{$deposit->id} verified")
            ->on($deposit->deposit_date)
            ->source($deposit)
            ->key('deposit:'.$deposit->id)
            ->by($by)
            ->debit(Account::Bank, $amount, ['fund_cycle_id' => null])
            ->credit(Account::MemberBalance, $amount, ['user_id' => $deposit->user_id])
            ->post();
    }

    public function chargeSettled(ChargeAllocation $allocation, ?User $by = null): void
    {
        $allocation->loadMissing('charge.member', 'charge.category');
        $member = $allocation->charge->member;
        $amount = Money::toPaisa($allocation->amount);
        $incomeAccount = $allocation->charge->category?->code === ChargeCategory::CODE_REGISTRATION_FEE
            ? Account::RegistrationFeeIncome
            : Account::OtherFeeIncome;
        $dims = ['user_id' => $member->managed_by_user_id, 'member_id' => $member->id];

        $this->ledger->entry('fee_settled', sprintf(
            '%s settled for %s',
            $allocation->charge->category?->title ?? 'Charge',
            $member->full_name,
        ))
            ->on($allocation->confirmed_at)
            ->source($allocation)
            ->key('charge-allocation:'.$allocation->id)
            ->by($by)
            ->debit(Account::MemberBalance, $amount, $dims)
            ->credit($incomeAccount, $amount, $dims)
            ->post();
    }

    public function chargeAllocationReversed(ChargeAllocation $allocation, ?User $by = null): void
    {
        $this->ledger->reverseSource($allocation, "Charge allocation #{$allocation->id} reversed", $by);
    }

    public function cycleAllocated(FundCycleAllocation $allocation, ?User $by = null): void
    {
        $allocation->loadMissing('member', 'fundCycle');
        $member = $allocation->member;
        $amount = Money::toPaisa($allocation->amount);
        $cycleId = (int) $allocation->fund_cycle_id;

        $this->ledger->entry('cycle_allocated', sprintf(
            '%s allocated to %s (%s)',
            $member->full_name,
            $allocation->fundCycle->name,
            $allocation->slot_key,
        ))
            ->on($allocation->allocated_at)
            ->source($allocation)
            ->key('cycle-allocation:'.$allocation->id)
            ->by($by)
            ->debit(Account::MemberBalance, $amount, ['user_id' => $member->managed_by_user_id, 'member_id' => $member->id])
            ->credit(Account::CycleCapital, $amount, [
                'user_id' => $member->managed_by_user_id,
                'member_id' => $member->id,
                'fund_cycle_id' => $cycleId,
            ])
            ->debit(Account::Bank, $amount, ['fund_cycle_id' => $cycleId], 'earmark to cycle')
            ->credit(Account::Bank, $amount, ['fund_cycle_id' => null], 'earmark to cycle')
            ->post();
    }

    /**
     * Allocate a member's slot after confirming the manager's pool covers it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function allocateToCycle(array $attributes, int $managerUserId): FundCycleAllocation
    {
        return DB::transaction(function () use ($attributes, $managerUserId): FundCycleAllocation {
            $this->lockAndAssertAvailable(
                $managerUserId,
                Money::toPaisa($attributes['amount']),
                'slot_key',
                'Not enough available balance for this slot allocation.',
            );

            // The model posts the journal entry on create.
            return FundCycleAllocation::query()->create($attributes);
        });
    }

    public function payoutPaid(PayoutRequest $payout, ?User $by = null): void
    {
        $amount = Money::toPaisa($payout->amount);

        $this->ledger->entry('member_payout', "Payout #{$payout->id} paid")
            ->on($payout->processed_at ?? now())
            ->source($payout)
            ->key('payout:'.$payout->id)
            ->by($by)
            ->debit(Account::MemberBalance, $amount, ['user_id' => $payout->user_id])
            ->credit(Account::Bank, $amount, ['fund_cycle_id' => null])
            ->post();
    }
}
