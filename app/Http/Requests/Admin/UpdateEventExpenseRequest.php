<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('paid_from'))) {
            $this->merge(['paid_from' => 'cash']);
        }
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', Rule::in(EventExpenseCategory::values())],
            'paid_from' => ['required', 'string', Rule::in(['cash', 'bkash', 'bank'])],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
        ];
    }
}
