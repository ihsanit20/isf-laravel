<?php

namespace App\Http\Requests\Admin;

use App\Models\FundCycleEvent;
use App\Services\FundCycleWithdrawalBudgetService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEventBankWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'withdrawal_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_no' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var FundCycleEvent $fundCycleEvent */
            $fundCycleEvent = $this->route('fundCycleEvent');

            app(FundCycleWithdrawalBudgetService::class)->assertCanWithdraw(
                $fundCycleEvent,
                (int) $this->integer('amount'),
            );
        });
    }
}
