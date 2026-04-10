<?php

namespace App\Http\Requests\Admin;

use App\Models\Charge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                Charge::STATUS_POSTED,
                Charge::STATUS_WAIVED,
                Charge::STATUS_CANCELLED,
            ])],
        ];
    }
}
