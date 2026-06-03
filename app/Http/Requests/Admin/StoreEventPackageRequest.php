<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventPackageStatus;
use App\Enums\EventPackageUnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'unit_type' => ['required', 'string', Rule::in(EventPackageUnitType::values())],
            'unit_size' => ['required', 'numeric', 'min:0.001'],
            'package_price' => ['required', 'numeric', 'min:0'],
            'advance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_qty_per_order' => ['required', 'integer', 'min:1'],
            'max_qty_per_order' => ['nullable', 'integer', 'min:1', 'gte:min_qty_per_order'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(EventPackageStatus::values())],
        ];
    }
}
