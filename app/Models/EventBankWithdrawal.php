<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'fund_cycle_event_id',
    'withdrawal_date',
    'amount',
    'description',
    'reference_no',
    'created_by_user_id',
])]
class EventBankWithdrawal extends Model
{
    protected function casts(): array
    {
        return [
            'withdrawal_date' => 'date',
            'amount' => 'integer',
        ];
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
