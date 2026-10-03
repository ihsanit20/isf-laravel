<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'fund_cycle_event_id',
    'income_date',
    'category',
    'received_via',
    'amount',
    'description',
    'receipt_path',
    'created_by_user_id',
])]
class EventIncome extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['scrap_sale', 'sponsorship', 'other'];

    protected function casts(): array
    {
        return [
            'income_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'scrap_sale' => 'Used item / scrap sale',
            'sponsorship' => 'Sponsorship',
            default => 'Other',
        };
    }

    public function fundCycleEvent(): BelongsTo
    {
        return $this->belongsTo(FundCycleEvent::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
