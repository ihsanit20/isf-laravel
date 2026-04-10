<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'status',
    'start_date',
    'lock_date',
    'maturity_date',
    'settlement_date',
    'notes',
    'created_by_user_id',
])]
class FundCycle extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_MATURED = 'matured';

    public const STATUS_SETTLED = 'settled';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_OPEN,
            self::STATUS_LOCKED,
            self::STATUS_MATURED,
            self::STATUS_SETTLED,
        ];
    }

    public static function statusLabel(string $value): string
    {
        return str($value)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'lock_date' => 'date',
            'maturity_date' => 'date',
            'settlement_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FundCycleAllocation::class);
    }
}
