<?php

namespace App\Http\Requests\Admin;

use App\Enums\MemberStatus;
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
            'status' => ['required', Rule::in([MemberStatus::Approved->value, MemberStatus::Rejected->value, MemberStatus::Exited->value])],
            'rejection_note' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn(): bool => $this->string('status')->toString() === MemberStatus::Rejected->value)],
        ];
    }
}
