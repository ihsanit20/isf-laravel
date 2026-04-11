<?php

namespace App\Http\Requests\Members;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Models\ChargeAllocation;
use App\Models\DepositSubmission;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMemberFundCycleAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');
        $user = $this->user();

        return $member instanceof Member && $user !== null && $member->managed_by_user_id === $user->id;
    }

    public function rules(): array
    {
        return [
            'slot_key' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Member $member */
            $member = $this->route('member');
            /** @var FundCycle $fundCycle */
            $fundCycle = $this->route('fundCycle');
            $user = $this->user();

            if (! $member instanceof Member || ! $fundCycle instanceof FundCycle || $user === null) {
                return;
            }

            if ($member->status !== MemberStatus::Approved || $member->activated_at === null) {
                $validator->errors()->add('slot_key', 'This member is not ready for fund cycle allocation.');

                return;
            }

            if ($fundCycle->status !== FundCycle::STATUS_OPEN) {
                $validator->errors()->add('slot_key', 'This fund cycle is no longer open for allocation.');

                return;
            }

            if ($fundCycle->lock_date !== null && now()->startOfDay()->greaterThanOrEqualTo($fundCycle->lock_date)) {
                $validator->errors()->add('slot_key', 'This fund cycle is locked and no longer accepts allocations.');

                return;
            }

            $slotKey = $this->string('slot_key')->trim()->toString();

            $availableSlots = collect($fundCycle->slots ?? [])
                ->map(fn($slot) => is_string($slot) ? trim($slot) : '')
                ->filter()
                ->values();

            if ($slotKey === '' || ! $availableSlots->contains($slotKey)) {
                $validator->errors()->add('slot_key', 'Select one of the configured cycle slots.');

                return;
            }

            $existingMemberAllocation = FundCycleAllocation::query()
                ->where('fund_cycle_id', $fundCycle->id)
                ->where('member_id', $member->id)
                ->where('slot_key', $slotKey)
                ->exists();

            if ($existingMemberAllocation) {
                $validator->errors()->add('slot_key', 'This slot is already allocated for this member.');

                return;
            }

            $allocationAmount = $fundCycle->allocationAmountFor($member->units);

            $verifiedDepositAmount = (int) DepositSubmission::query()
                ->where('user_id', $user->id)
                ->where('status', DepositSubmissionStatus::Verified)
                ->sum('amount');

            $chargeAllocatedAmount = (int) ChargeAllocation::query()
                ->whereNull('reversed_at')
                ->whereHas('charge.member', fn($query) => $query->where('managed_by_user_id', $user->id))
                ->sum('amount');

            $cycleAllocatedAmount = (int) FundCycleAllocation::query()
                ->whereHas('member', fn($query) => $query->where('managed_by_user_id', $user->id))
                ->sum('amount');

            $remainingPool = max(0, $verifiedDepositAmount - $chargeAllocatedAmount - $cycleAllocatedAmount);

            if ($allocationAmount > $remainingPool) {
                $validator->errors()->add('slot_key', 'You do not have enough verified deposit balance for this slot allocation.');
            }
        });
    }
}
