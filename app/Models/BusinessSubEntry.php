<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note-only record of the operator's own spending and earnings for a
 * business investment. It is never posted to the journal.
 */
#[Fillable([
    'cycle_investment_id',
    'direction',
    'category',
    'amount',
    'entry_date',
    'note',
    'created_by_user_id',
])]
class BusinessSubEntry extends Model
{
    public const DIRECTION_EXPENSE = 'expense';

    public const DIRECTION_INCOME = 'income';

    public const DIRECTIONS = [self::DIRECTION_EXPENSE, self::DIRECTION_INCOME];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
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
