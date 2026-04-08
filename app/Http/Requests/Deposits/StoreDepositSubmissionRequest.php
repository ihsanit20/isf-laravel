<?php

namespace App\Http\Requests\Deposits;

use App\Models\DepositSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepositSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', Rule::in(DepositSubmission::paymentMethods())],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'deposit_date' => ['required', 'date', 'before_or_equal:today'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
