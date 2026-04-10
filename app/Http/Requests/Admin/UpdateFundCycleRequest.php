<?php

namespace App\Http\Requests\Admin;

use App\Models\FundCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'start_date' => ['required', 'date'],
            'lock_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'maturity_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'settlement_date' => ['nullable', 'date', 'after_or_equal:maturity_date'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*' => ['required', 'string', 'max:100', 'distinct'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
