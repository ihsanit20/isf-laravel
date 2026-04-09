<?php

namespace App\Http\Requests\Deposits;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
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
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.member_id' => ['required', 'integer'],
            'rows.*.allocation_month' => ['required', 'date_format:Y-m'],
            'rows.*.units' => ['required', 'integer', 'min:1'],
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
                $validator->errors()->add('rows', 'No verified deposits are available for allocation.');

                return;
            }

            $rows = collect($this->input('rows', []));
            $members = $this->approvedMembersById($rows->pluck('member_id')->all());

            $totalAllocatedAmount = 0;

            foreach ($rows as $index => $row) {
                $member = $members->get((int) $row['member_id']);

                if (! $member instanceof Member) {
                    $validator->errors()->add("rows.{$index}.member_id", 'Select one of your approved members.');

                    continue;
                }

                if ((int) $row['units'] > $member->units) {
                    $validator->errors()->add(
                        "rows.{$index}.units",
                        "{$member->full_name} supports up to {$member->units} units per month.",
                    );
                }

                try {
                    \Carbon\CarbonImmutable::createFromFormat('Y-m', $row['allocation_month'])->startOfMonth();
                } catch (\Throwable) {
                    $validator->errors()->add("rows.{$index}.allocation_month", 'Select a valid month.');
                }

                $totalAllocatedAmount += (int) $row['units'] * DepositAllocation::DEFAULT_UNIT_AMOUNT;
            }

            $existingAllocatedAmount = DepositAllocation::query()
                ->whereHas('member', fn($query) => $query->where('managed_by_user_id', $user?->id))
                ->sum('allocated_amount');

            $totalAllocatableAmount = max(0, (int) $verifiedDeposits->sum('amount') - (int) $existingAllocatedAmount);

            if ($totalAllocatedAmount > $totalAllocatableAmount) {
                $validator->errors()->add('rows', 'Allocated amount cannot exceed the total allocatable verified deposit amount.');
            }
        });
    }

    private function approvedMembersById(array $memberIds): Collection
    {
        return Member::query()
            ->where('managed_by_user_id', $this->user()?->id)
            ->where('status', MemberStatus::Approved)
            ->whereIn('id', array_map('intval', $memberIds))
            ->get()
            ->keyBy('id');
    }
}
