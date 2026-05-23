<?php

namespace App\Models;

use App\Enums\EventOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'fund_cycle_event_id',
    'event_pickup_point_id',
    'order_number',
    'customer_name',
    'customer_phone',
    'customer_address',
    'status',
    'total_amount',
    'advance_amount',
    'confirmed_at',
])]
class EventOrder extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status'       => EventOrderStatus::class,
            'total_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function fundCycleEvent(): BelongsTo
    {
        return $this->belongsTo(FundCycleEvent::class);
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(EventPickupPoint::class, 'event_pickup_point_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EventPayment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EventOrderStatusHistory::class);
    }
}
