<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'fund_cycle_id',
    'type',
    'title',
    'counterparty',
    'terms',
    'invested_at',
    'status',
    'closed_at',
    'closed_by_user_id',
    'created_by_user_id',
])]
class CycleInvestment extends Model
{
    public const TYPE_EVENT = 'event';

    public const TYPE_BUSINESS = 'business';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'invested_at' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function fundCycle(): BelongsTo
    {
        return $this->belongsTo(FundCycle::class);
    }

    public function event(): HasOne
    {
        return $this->hasOne(FundCycleEvent::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(CycleInvestmentCharge::class);
    }

    public function businessTransactions(): HasMany
    {
        return $this->hasMany(BusinessTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isEvent(): bool
    {
        return $this->type === self::TYPE_EVENT;
    }

    public function ensureActive(): void
    {
        abort_unless($this->isActive(), 403, 'This investment has been closed or cancelled and is locked for changes.');
    }

    /**
     * @return array{fund_cycle_id: int, cycle_investment_id: int}
     */
    public function dimensions(): array
    {
        return [
            'fund_cycle_id' => (int) $this->fund_cycle_id,
            'cycle_investment_id' => (int) $this->id,
        ];
    }
}
