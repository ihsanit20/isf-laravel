<?php

namespace App\Http\Requests\Admin;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([Member::STATUS_APPROVED, Member::STATUS_REJECTED])],
            'rejection_note' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn(): bool => $this->string('status')->toString() === Member::STATUS_REJECTED)],
        ];
    }
}
