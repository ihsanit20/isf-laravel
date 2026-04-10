<?php

namespace App\Http\Requests\Admin;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Models\ChargeAllocation;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFundCycleAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var FundCycle $fundCycle */
            $fundCycle = $this->route('fundCycle');

            $member = Member::query()->find((int) $this->input('member_id'));

            if (! $member instanceof Member || $member->status !== MemberStatus::Approved) {
                $validator->errors()->add('member_id', 'Select an approved member.');

                return;
            }

            $amount = (int) $this->input('amount');

            $verifiedDepositAmount = (int) DepositSubmission::query()
                ->where('status', DepositSubmissionStatus::Verified)
                ->sum('amount');

            $chargeAllocatedAmount = (int) ChargeAllocation::query()
                ->whereNull('reversed_at')
                ->sum('amount');

            $cycleAllocatedAmount = (int) FundCycleAllocation::query()
                ->where('fund_cycle_id', '!=', $fundCycle->id)
                ->sum('amount');

            $remainingPool = max(0, $verifiedDepositAmount - $chargeAllocatedAmount - $cycleAllocatedAmount);

            if ($amount > $remainingPool) {
                $validator->errors()->add('amount', 'Allocation cannot exceed the remaining verified deposit pool.');
            }

            $existingMemberAllocation = FundCycleAllocation::query()
                ->where('fund_cycle_id', $fundCycle->id)
                ->where('member_id', $member->id)
                ->exists();

            if ($existingMemberAllocation) {
                $validator->errors()->add('member_id', 'This member is already allocated in the selected fund cycle.');
            }
        });
    }
}
