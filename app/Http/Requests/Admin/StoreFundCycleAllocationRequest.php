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
            'slot_key' => ['required', 'string', 'max:100'],
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

            $slotKey = $this->string('slot_key')->toString();

            $availableSlots = collect($fundCycle->slots ?? [])
                ->map(fn($slot) => is_string($slot) ? trim($slot) : '')
                ->filter()
                ->values();

            if ($slotKey === '' || ! $availableSlots->contains($slotKey)) {
                $validator->errors()->add('slot_key', 'Select one of the configured cycle slots.');

                return;
            }

            $amount = $fundCycle->allocationAmountFor($member->units);

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
                $validator->errors()->add('member_id', 'Allocation cannot exceed the remaining verified deposit pool.');
            }

            $existingMemberAllocation = FundCycleAllocation::query()
                ->where('fund_cycle_id', $fundCycle->id)
                ->where('member_id', $member->id)
                ->where('slot_key', $slotKey)
                ->exists();

            if ($existingMemberAllocation) {
                $validator->errors()->add('slot_key', 'This member is already allocated for the selected slot in this fund cycle.');
            }
        });
    }
}
