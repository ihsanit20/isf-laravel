<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        return $actor instanceof User
            && $target instanceof User
            && $actor->hasAdminAccess()
            && $actor->canManageUser($target);
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            ...$this->profileRules($target->id),
            'role' => ['required', 'string', Rule::in(User::assignableRolesFor((string) $this->user()?->role))],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'You may not assign a role higher than your own.',
        ];
    }
}
