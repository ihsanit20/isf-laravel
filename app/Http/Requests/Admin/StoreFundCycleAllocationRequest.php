<?php

namespace App\Http\Requests\Admin;

use App\Enums\MemberStatus;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
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
                ->map(fn ($slot) => is_string($slot) ? trim($slot) : '')
                ->filter()
                ->values();

            if ($slotKey === '' || ! $availableSlots->contains($slotKey)) {
                $validator->errors()->add('slot_key', 'Select one of the configured cycle slots.');

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

            $amount = $fundCycle->allocationAmountFor($member->units);
            $remainingPool = app(MemberPostings::class)->availableBalance((int) $member->managed_by_user_id);

            if (Money::toPaisa($amount) > $remainingPool) {
                $validator->errors()->add('member_id', 'Allocation cannot exceed the available balance of this member\'s account holder.');
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
