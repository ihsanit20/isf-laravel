<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'fund_cycle_id',
    'direction',
    'category',
    'amount',
    'transaction_date',
    'description',
    'receipt_path',
    'created_by_user_id',
])]
class FundCycleTransaction extends Model
{
    use SoftDeletes;

    public const DIRECTIONS = ['income', 'expense'];

    public const CATEGORIES = ['documentation', 'legal', 'bank_charge', 'other'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'documentation' => 'Documentation',
            'legal' => 'Legal',
            'bank_charge' => 'Bank charge',
            default => 'Other',
        };
    }

    public function fundCycle(): BelongsTo
    {
        return $this->belongsTo(FundCycle::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
