<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'title',
    'amount',
    'status',
    'effective_at',
    'settled_at',
    'settled_by_user_id',
])]
class Charge extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'effective_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_POSTED,
            self::STATUS_WAIVED,
            self::STATUS_CANCELLED,
        ];
    }

    public function chargeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }
}
