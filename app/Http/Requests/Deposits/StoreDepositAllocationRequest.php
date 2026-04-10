<?php

namespace App\Http\Requests\Deposits;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\DepositAllocation;
use App\Models\DepositSubmission;
use App\Models\Member;
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
            'unit_rows' => ['nullable', 'array'],
            'unit_rows.*.member_id' => ['required', 'integer'],
            'unit_rows.*.allocation_month' => ['required', 'date_format:Y-m'],
            'unit_rows.*.units' => ['required', 'integer', 'min:1'],
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
                $validator->errors()->add('unit_rows', 'No verified deposits are available for allocation.');

                return;
            }

            $rows = collect($this->input('unit_rows', []));
            $chargeIds = collect($this->input('charge_ids', []))->map(fn($id) => (int) $id)->filter();

            if ($rows->isEmpty() && $chargeIds->isEmpty()) {
                $validator->errors()->add('unit_rows', 'Add at least one unit allocation or one charge allocation.');

                return;
            }

            $members = $this->activeMembersById($rows->pluck('member_id')->all());
            $charges = $this->pendingChargesById($chargeIds->all());

            $totalAllocatedAmount = 0;

            foreach ($rows as $index => $row) {
                $member = $members->get((int) $row['member_id']);

                if (! $member instanceof Member) {
                    $validator->errors()->add("unit_rows.{$index}.member_id", 'Select one of your active members.');

                    continue;
                }

                if ((int) $row['units'] > $member->units) {
                    $validator->errors()->add(
                        "unit_rows.{$index}.units",
                        "{$member->full_name} supports up to {$member->units} units per month.",
                    );
                }

                try {
                    \Carbon\CarbonImmutable::createFromFormat('Y-m', $row['allocation_month'])->startOfMonth();
                } catch (\Throwable) {
                    $validator->errors()->add("unit_rows.{$index}.allocation_month", 'Select a valid month.');
                }

                $totalAllocatedAmount += (int) $row['units'] * DepositAllocation::DEFAULT_UNIT_AMOUNT;
            }

            foreach ($chargeIds as $index => $chargeId) {
                $charge = $charges->get($chargeId);

                if (! $charge instanceof Charge) {
                    $validator->errors()->add("charge_ids.{$index}", 'Select one of your pending charges.');

                    continue;
                }

                $totalAllocatedAmount += $charge->amount;
            }

            $existingAllocatedAmount = DepositAllocation::query()
                ->whereHas('member', fn($query) => $query->where('managed_by_user_id', $user?->id))
                ->sum('allocated_amount');

            $existingChargeAllocatedAmount = ChargeAllocation::query()
                ->whereNull('reversed_at')
                ->whereHas('charge.member', fn($query) => $query->where('managed_by_user_id', $user?->id))
                ->sum('amount');

            $totalAllocatableAmount = max(0, (int) $verifiedDeposits->sum('amount') - (int) $existingAllocatedAmount - (int) $existingChargeAllocatedAmount);

            if ($totalAllocatedAmount > $totalAllocatableAmount) {
                $validator->errors()->add('unit_rows', 'Allocated amount cannot exceed the total allocatable verified deposit amount.');
            }
        });
    }

    private function activeMembersById(array $memberIds): Collection
    {
        return Member::query()
            ->where('managed_by_user_id', $this->user()?->id)
            ->where('status', MemberStatus::Approved)
            ->whereNotNull('activated_at')
            ->whereIn('id', array_map('intval', $memberIds))
            ->get()
            ->keyBy('id');
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
