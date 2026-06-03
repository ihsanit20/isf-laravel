<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(EventOrderStatus::values())],
            'note' => ['nullable', 'string', 'max:500'],
            'allow_delivered_with_due' => ['sometimes', 'boolean'],
        ];
    }
}
