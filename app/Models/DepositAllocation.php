<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'member_id',
    'allocation_month',
    'units',
    'unit_amount',
    'allocated_amount',
    'confirmed_at',
])]
class DepositAllocation extends Model
{
    public const DEFAULT_UNIT_AMOUNT = 1000;

    protected function casts(): array
    {
        return [
            'allocation_month' => 'date',
            'units' => 'integer',
            'unit_amount' => 'integer',
            'allocated_amount' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function scopeNewestFirst($query)
    {
        return $query
            ->orderByDesc('allocation_month')
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id');
    }
}
