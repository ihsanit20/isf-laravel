<?php

namespace App\Http\Requests\Deposits;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\DepositSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class StoreDepositAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'charge_ids' => ['nullable', 'array'],
            'charge_ids.*' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            $verifiedDeposits = DepositSubmission::query()
                ->where('user_id', $user?->id)
                ->where('status', DepositSubmissionStatus::Verified->value)
                ->get();

            if ($verifiedDeposits->isEmpty()) {
                $validator->errors()->add('charge_ids', 'No verified deposits are available for charge settlement.');

                return;
            }

            $chargeIds = collect($this->input('charge_ids', []))->map(fn($id) => (int) $id)->filter();

            if ($chargeIds->isEmpty()) {
                $validator->errors()->add('charge_ids', 'Select at least one pending charge to settle.');

                return;
            }

            $charges = $this->pendingChargesById($chargeIds->all());

            $totalAllocatedAmount = 0;

            foreach ($chargeIds as $index => $chargeId) {
                $charge = $charges->get($chargeId);

                if (! $charge instanceof Charge) {
                    $validator->errors()->add("charge_ids.{$index}", 'Select one of your pending charges.');

                    continue;
                }

                $totalAllocatedAmount += $charge->amount;
            }

            $existingChargeAllocatedAmount = ChargeAllocation::query()
                ->whereNull('reversed_at')
                ->whereHas('charge.member', fn($query) => $query->where('managed_by_user_id', $user?->id))
                ->sum('amount');

            $totalAllocatableAmount = max(0, (int) $verifiedDeposits->sum('amount') - (int) $existingChargeAllocatedAmount);

            if ($totalAllocatedAmount > $totalAllocatableAmount) {
                $validator->errors()->add('charge_ids', 'Charge allocation cannot exceed the total allocatable verified deposit amount.');
            }
        });
    }

    private function pendingChargesById(array $chargeIds): Collection
    {
        return Charge::query()
            ->where('status', Charge::STATUS_PENDING)
            ->whereIn('id', array_map('intval', $chargeIds))
            ->whereHas('member', fn($query) => $query
                ->where('managed_by_user_id', $this->user()?->id)
                ->where('status', MemberStatus::Approved))
            ->get()
            ->keyBy('id');
    }
}
