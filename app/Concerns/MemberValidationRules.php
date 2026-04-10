<?php

namespace App\Concerns;

use App\Models\Member;
use Illuminate\Validation\Rule;

trait MemberValidationRules
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function memberRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'relationship_to_user' => ['required', 'string', Rule::in(Member::relationshipOptions())],
            'units' => ['required', 'integer', 'min:1'],
        ];
    }
}
