<?php

namespace App\Models;

use App\Ledger\Postings\MemberPostings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'charge_id',
    'amount',
    'confirmed_at',
    'reversed_at',
    'reversed_by_user_id',
])]
class ChargeAllocation extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $allocation) => app(MemberPostings::class)->chargeSettled($allocation, auth()->user()));

        static::updated(function (self $allocation): void {
            if ($allocation->wasChanged('reversed_at') && $allocation->reversed_at !== null) {
                app(MemberPostings::class)->chargeAllocationReversed($allocation, auth()->user());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'confirmed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }
}
