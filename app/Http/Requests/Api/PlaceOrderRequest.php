<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'event_slug'              => ['required', 'string'],
            'customer_name'           => ['required', 'string', 'max:100'],
            'customer_phone'          => ['required', 'string', 'max:20'],
            'customer_address'        => ['nullable', 'string', 'max:500'],
            'pickup_point_id'         => ['nullable', 'integer', 'exists:event_pickup_points,id'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.package_id'      => ['required', 'integer', 'exists:event_packages,id'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'              => 'At least one package must be selected.',
            'items.*.package_id.exists'   => 'One or more selected packages are invalid.',
            'items.*.quantity.min'        => 'Quantity must be at least 1.',
        ];
    }
}
