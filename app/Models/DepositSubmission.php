<?php

namespace App\Models;

use App\Enums\DepositSubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'amount',
    'payment_method',
    'reference_no',
    'deposit_date',
    'proof_path',
    'notes',
    'status',
    'verified_at',
    'verified_by_user_id',
    'rejection_reason',
])]
class DepositSubmission extends Model
{
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_METHOD_CASH_DEPOSIT = 'cash_deposit';

    public const PAYMENT_METHOD_MOBILE_BANKING = 'mobile_banking';

    public const PAYMENT_METHOD_OTHER = 'other';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'deposit_date' => 'date',
            'status' => DepositSubmissionStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public static function paymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_BANK_TRANSFER,
            self::PAYMENT_METHOD_CASH_DEPOSIT,
            self::PAYMENT_METHOD_MOBILE_BANKING,
            self::PAYMENT_METHOD_OTHER,
        ];
    }

    public static function paymentMethodLabel(string $value): string
    {
        return str($value)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
