<?php

namespace App\Http\Requests\Admin;

use App\Models\FundCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFundCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(FundCycle::statuses())],
            'unit_amount' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'lock_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'maturity_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'settlement_date' => ['nullable', 'date', 'after_or_equal:maturity_date'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*' => ['required', 'string', 'max:100', 'distinct'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var FundCycle $fundCycle */
            $fundCycle = $this->route('fundCycle');

            if (! $fundCycle instanceof FundCycle) {
                return;
            }

            $nextUnitAmount = (int) $this->input('unit_amount');

            if ($fundCycle->allocations()->exists() && $fundCycle->unit_amount !== $nextUnitAmount) {
                $validator->errors()->add('unit_amount', 'Unit amount cannot be changed after allocations have been recorded for this cycle.');
            }
        });
    }
}
