<?php

namespace App\Http\Requests\Members;

use App\Concerns\MemberValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    use MemberValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return $this->memberRules();
    }
}
