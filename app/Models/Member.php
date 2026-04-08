<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'managed_by_user_id',
    'full_name',
    'phone',
    'relationship_to_user',
    'units',
    'registration_fee_amount',
    'registration_fee_payment_method',
    'registration_fee_reference_no',
    'registration_fee_proof_path',
    'status',
    'applied_at',
    'approved_at',
    'approved_by_user_id',
    'rejection_note',
])]
class Member extends Model
{
    public const REGISTRATION_FEE_AMOUNT = 100;

    public const RELATIONSHIP_SELF = 'self';

    public const RELATIONSHIP_SPOUSE = 'spouse';

    public const RELATIONSHIP_CHILD = 'child';

    public const RELATIONSHIP_PARENT = 'parent';

    public const RELATIONSHIP_OTHER = 'other';

    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_METHOD_CASH_DEPOSIT = 'cash_deposit';

    public const PAYMENT_METHOD_MOBILE_BANKING = 'mobile_banking';

    public const PAYMENT_METHOD_OTHER = 'other';

    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'units' => 'integer',
            'registration_fee_amount' => 'integer',
        ];
    }

    public static function relationshipOptions(): array
    {
        return [
            self::RELATIONSHIP_SELF,
            self::RELATIONSHIP_SPOUSE,
            self::RELATIONSHIP_CHILD,
            self::RELATIONSHIP_PARENT,
            self::RELATIONSHIP_OTHER,
        ];
    }

    public static function registrationFeePaymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_BANK_TRANSFER,
            self::PAYMENT_METHOD_CASH_DEPOSIT,
            self::PAYMENT_METHOD_MOBILE_BANKING,
            self::PAYMENT_METHOD_OTHER,
        ];
    }

    public static function paymentMethodLabel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str($value)->replace('_', ' ')->title()->toString();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function depositAllocations(): HasMany
    {
        return $this->hasMany(DepositAllocation::class);
    }
}
