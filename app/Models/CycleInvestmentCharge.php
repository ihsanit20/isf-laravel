<?php

namespace App\Models;

use App\Ledger\Account;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cycle_investment_id',
    'type',
    'amount',
    'note',
    'charged_at',
    'created_by_user_id',
])]
class CycleInvestmentCharge extends Model
{
    use SoftDeletes;

    public const TYPES = ['platform_service', 'asset_rent', 'other'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'charged_at' => 'date',
        ];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'platform_service' => 'Platform service charge',
            'asset_rent' => 'Asset rent',
            default => 'Other platform charge',
        };
    }

    /**
     * @return array{0: Account, 1: Account} [fund expense, platform income]
     */
    public function accounts(): array
    {
        return match ($this->type) {
            'platform_service' => [Account::PlatformServiceCharge, Account::PlatformServiceIncome],
            'asset_rent' => [Account::AssetRent, Account::AssetRentIncome],
            default => [Account::OtherPlatformCharge, Account::OtherChargeIncome],
        };
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(CycleInvestment::class, 'cycle_investment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
