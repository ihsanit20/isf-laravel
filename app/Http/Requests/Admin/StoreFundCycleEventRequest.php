<?php

namespace App\Http\Requests\Admin;

use App\Enums\FundCycleEventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundCycleEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(FundCycleEventStatus::values())],
            'description' => ['nullable', 'string'],
            'banner_image_path' => ['nullable', 'string', 'max:255'],
            'order_open_at' => ['required', 'date'],
            'order_close_at' => ['required', 'date', 'after:order_open_at'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_close_at'],
        ];
    }
}
