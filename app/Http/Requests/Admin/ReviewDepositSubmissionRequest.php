<?php

namespace App\Http\Requests\Admin;

use App\Enums\DepositSubmissionStatus;
use App\Models\DepositSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewDepositSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                DepositSubmissionStatus::Verified->value,
                DepositSubmissionStatus::Rejected->value,
            ])],
            'rejection_reason' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(
                    fn(): bool => $this->string('status')->toString() === DepositSubmissionStatus::Rejected->value,
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var DepositSubmission|null $depositSubmission */
            $depositSubmission = $this->route('depositSubmission');

            if (! $depositSubmission instanceof DepositSubmission) {
                return;
            }

            if ($depositSubmission->status !== DepositSubmissionStatus::Pending) {
                $validator->errors()->add('status', 'Only pending deposits can be reviewed.');
            }
        });
    }
}
