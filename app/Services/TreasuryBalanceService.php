<?php

namespace App\Services;

use App\Enums\DepositSubmissionStatus;
use App\Models\ChargeAllocation;
use App\Models\DepositSubmission;
use App\Models\EventBankDeposit;
use App\Models\EventBankWithdrawal;
use App\Models\GeneralExpense;

class TreasuryBalanceService
{
    /**
     * @return array{
     *     total_deposit_amount: int,
     *     verified_amount: int,
     *     rejected_amount: int,
     *     total_general_expense: int,
     *     total_event_bank_withdrawals: int,
     *     total_event_bank_deposits: int,
     *     total_charge_settlements: int,
     *     current_balance: int,
     *     pending_amount: int,
     *     pending_count: int,
     * }
     */
    public function summary(): array
    {
        $totalDepositAmount = (int) DepositSubmission::query()->sum('amount');
        $verifiedAmount = (int) DepositSubmission::query()
            ->where('status', DepositSubmissionStatus::Verified)
            ->sum('amount');
        $rejectedAmount = (int) DepositSubmission::query()
            ->where('status', DepositSubmissionStatus::Rejected)
            ->sum('amount');
        $totalGeneralExpense = (int) GeneralExpense::query()->sum('amount');
        $totalEventBankWithdrawals = (int) EventBankWithdrawal::query()->sum('amount');
        $totalEventBankDeposits = (int) EventBankDeposit::query()->sum('amount');
        $totalChargeSettlements = (int) ChargeAllocation::query()
            ->whereNull('reversed_at')
            ->sum('amount');
        $pendingAmount = (int) DepositSubmission::query()
            ->where('status', DepositSubmissionStatus::Pending)
            ->sum('amount');
        $pendingCount = (int) DepositSubmission::query()
            ->where('status', DepositSubmissionStatus::Pending)
            ->count();

        $currentBalance = max(
            0,
            $verifiedAmount
                - $totalGeneralExpense
                - $totalEventBankWithdrawals
                + $totalEventBankDeposits,
        );

        return [
            'total_deposit_amount' => $totalDepositAmount,
            'verified_amount' => $verifiedAmount,
            'rejected_amount' => $rejectedAmount,
            'total_general_expense' => $totalGeneralExpense,
            'total_event_bank_withdrawals' => $totalEventBankWithdrawals,
            'total_event_bank_deposits' => $totalEventBankDeposits,
            'total_charge_settlements' => $totalChargeSettlements,
            'current_balance' => $currentBalance,
            'pending_amount' => $pendingAmount,
            'pending_count' => $pendingCount,
        ];
    }
}
