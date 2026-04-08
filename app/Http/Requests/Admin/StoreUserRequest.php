<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'role' => ['required', 'string', Rule::in(User::assignableRolesFor((string) $this->user()?->role))],
            'password' => $this->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'You may not assign a role higher than your own.',
        ];
    }
}