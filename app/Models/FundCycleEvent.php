<?php

namespace App\Models;

use App\Enums\FundCycleEventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'fund_cycle_id',
    'title',
    'slug',
    'status',
    'description',
    'banner_image_path',
    'order_open_at',
    'order_close_at',
    'expected_delivery_date',
])]
class FundCycleEvent extends Model
{
    protected function casts(): array
    {
        return [
            'status' => FundCycleEventStatus::class,
            'order_open_at' => 'datetime',
            'order_close_at' => 'datetime',
            'expected_delivery_date' => 'date',
        ];
    }

    public function fundCycle(): BelongsTo
    {
        return $this->belongsTo(FundCycle::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(EventPackage::class);
    }

    public function pickupPoints(): HasMany
    {
        return $this->hasMany(EventPickupPoint::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EventOrder::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(EventExpense::class);
    }

    public function bankWithdrawals(): HasMany
    {
        return $this->hasMany(EventBankWithdrawal::class);
    }

    public static function bannerDisk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    public function bannerUrl(): ?string
    {
        if ($this->banner_image_path === null) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::bannerDisk());

        return $disk->url($this->banner_image_path);
    }
}
