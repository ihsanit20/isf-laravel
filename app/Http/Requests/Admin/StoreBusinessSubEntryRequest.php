<?php

namespace App\Http\Requests\Admin;

use App\Models\BusinessSubEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessSubEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('category'))) {
            $this->merge(['category' => trim($this->input('category'))]);
        }
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', Rule::in(BusinessSubEntry::DIRECTIONS)],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'entry_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
