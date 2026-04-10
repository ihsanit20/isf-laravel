<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'fund_cycle_id',
    'member_id',
    'slot_key',
    'amount',
    'allocated_at',
    'notes',
    'created_by_user_id',
])]
class FundCycleAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'allocated_at' => 'datetime',
        ];
    }

    public function fundCycle(): BelongsTo
    {
        return $this->belongsTo(FundCycle::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
