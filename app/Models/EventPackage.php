<?php

namespace App\Models;

use App\Enums\EventPackageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'fund_cycle_event_id',
    'name',
    'description',
    'unit_price',
    'advance_percent',
    'min_qty_per_order',
    'max_qty_per_order',
    'stock_qty',
    'sold_qty',
    'sort_order',
    'status',
])]
class EventPackage extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => EventPackageStatus::class,
            'unit_price' => 'decimal:2',
            'advance_percent' => 'decimal:2',
        ];
    }

    public function fundCycleEvent(): BelongsTo
    {
        return $this->belongsTo(FundCycleEvent::class);
    }

    public function remainingQty(): ?int
    {
        if ($this->stock_qty === null) {
            return null;
        }

        return max(0, $this->stock_qty - $this->sold_qty);
    }
}
