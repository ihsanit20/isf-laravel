<?php

namespace App\Models;

use App\Enums\EventPackageUnitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_order_id',
    'event_package_id',
    'quantity',
    'unit_type',
    'unit_size',
    'package_price',
    'line_total',
])]
class EventOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_type' => EventPackageUnitType::class,
            'unit_size' => 'decimal:3',
            'package_price' => 'decimal:2',
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

    public function physicalQuantity(): float
    {
        if ($this->unit_type === null || $this->unit_size === null) {
            return (float) $this->quantity;
        }

        return (float) $this->unit_size * $this->quantity;
    }

    public function unitLabel(): ?string
    {
        if ($this->unit_type === null || $this->unit_size === null) {
            return null;
        }

        return $this->unit_type->formatSize($this->unit_size);
    }

    public function quantityLabel(): string
    {
        if ($this->unit_type === null || $this->unit_size === null) {
            return (string) $this->quantity;
        }

        return EventPackageUnitType::formatPackLine(
            $this->unit_size,
            $this->unit_type,
            $this->quantity,
        );
    }

    public function packageSizeLineLabel(): string
    {
        if ($this->unit_type === null || $this->unit_size === null) {
            return (string) $this->quantity;
        }

        return EventPackageUnitType::formatPackSizeLine(
            $this->unit_size,
            $this->unit_type,
            $this->quantity,
        );
    }
}
