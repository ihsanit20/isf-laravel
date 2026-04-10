<?php

namespace App\Http\Requests\Admin;

use App\Models\ChargeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargeCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        /** @var ChargeCategory $chargeCategory */
        $chargeCategory = $this->route('chargeCategory');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('charge_categories', 'code')->ignore($chargeCategory->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($chargeCategory): void {
                    if ($chargeCategory->code === ChargeCategory::CODE_REGISTRATION_FEE && $value !== $chargeCategory->code) {
                        $fail('The registration fee category code cannot be changed.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:255'],
            'default_amount' => ['required', 'integer', 'min:1'],
            'is_active' => [
                'required',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail) use ($chargeCategory): void {
                    if ($chargeCategory->code === ChargeCategory::CODE_REGISTRATION_FEE && ! filter_var($value, FILTER_VALIDATE_BOOL)) {
                        $fail('The registration fee category must stay active.');
                    }
                },
            ],
        ];
    }
}
