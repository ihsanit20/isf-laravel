<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_order_id',
    'event_package_id',
    'quantity',
    'unit_price',
    'line_total',
])]
class EventOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EventOrder::class, 'event_order_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(EventPackage::class, 'event_package_id');
    }
}
