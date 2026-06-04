<?php

namespace App\Services;

use App\Models\EventBankWithdrawal;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleEvent;
use Illuminate\Validation\ValidationException;

class FundCycleWithdrawalBudgetService
{
    /**
     * @return array{
     *     allocated_amount: int,
     *     withdrawn_amount: int,
     *     remaining_amount: int,
     * }
     */
    public function forCycle(int $fundCycleId, ?int $excludeWithdrawalId = null): array
    {
        $allocatedAmount = (int) FundCycleAllocation::query()
            ->where('fund_cycle_id', $fundCycleId)
            ->sum('amount');

        $withdrawnAmount = $this->withdrawnAmountForCycle($fundCycleId, $excludeWithdrawalId);
        $remainingAmount = max(0, $allocatedAmount - $withdrawnAmount);

        return [
            'allocated_amount' => $allocatedAmount,
            'withdrawn_amount' => $withdrawnAmount,
            'remaining_amount' => $remainingAmount,
        ];
    }

    public function assertCanWithdraw(
        FundCycleEvent $fundCycleEvent,
        int $amount,
        ?EventBankWithdrawal $existing = null,
    ): void {
        $fundCycleId = (int) $fundCycleEvent->fund_cycle_id;
        $budget = $this->forCycle($fundCycleId, $existing?->id);

        if ($budget['allocated_amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'No member allocations exist for this fund cycle yet. Record allocations before withdrawing from the bank.',
            ]);
        }

        if ($amount > $budget['remaining_amount']) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'This withdrawal would exceed the fund cycle allocation budget (allocated: %s BDT, already withdrawn: %s BDT, remaining: %s BDT).',
                    number_format($budget['allocated_amount']),
                    number_format($budget['withdrawn_amount']),
                    number_format($budget['remaining_amount']),
                ),
            ]);
        }
    }

    private function withdrawnAmountForCycle(int $fundCycleId, ?int $excludeWithdrawalId = null): int
    {
        return (int) EventBankWithdrawal::query()
            ->whereHas(
                'fundCycleEvent',
                fn ($query) => $query->where('fund_cycle_id', $fundCycleId),
            )
            ->when(
                $excludeWithdrawalId !== null,
                fn ($query) => $query->where('id', '!=', $excludeWithdrawalId),
            )
            ->sum('amount');
    }
}
