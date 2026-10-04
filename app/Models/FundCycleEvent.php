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
    'cycle_investment_id',
    'title',
    'slug',
    'status',
    'is_finalized',
    'description',
    'banner_image_path',
    'order_open_at',
    'order_close_at',
    'expected_delivery_date',
])]
class FundCycleEvent extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $event) => $event->ensureInvestment());

        static::updated(function (self $event): void {
            if ($event->wasChanged('title') && $event->investment !== null) {
                $event->investment->update(['title' => $event->title]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => FundCycleEventStatus::class,
            'is_finalized' => 'boolean',
            'order_open_at' => 'datetime',
            'order_close_at' => 'datetime',
            'expected_delivery_date' => 'date',
        ];
    }

    public function fundCycle(): BelongsTo
    {
        return $this->belongsTo(FundCycle::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(CycleInvestment::class, 'cycle_investment_id');
    }

    /**
     * Every event is a sub-business (cycle investment) for the ledger.
     */
    public function ensureInvestment(): CycleInvestment
    {
        if ($this->investment !== null) {
            return $this->investment;
        }

        $investment = CycleInvestment::query()->create([
            'fund_cycle_id' => $this->fund_cycle_id,
            'type' => CycleInvestment::TYPE_EVENT,
            'title' => $this->title,
            'invested_at' => $this->order_open_at?->toDateString(),
        ]);

        $this->forceFill(['cycle_investment_id' => $investment->id])->saveQuietly();
        $this->setRelation('investment', $investment);

        return $investment;
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(EventIncome::class);
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

    public function bankDeposits(): HasMany
    {
        return $this->hasMany(EventBankDeposit::class);
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

    public function ensureNotFinalized(): void
    {
        abort_if($this->is_finalized, 403, 'This event has been finalized and is locked for changes.');
        abort_if($this->status === FundCycleEventStatus::Cancelled, 403, 'This event has been cancelled and is locked for changes.');
    }
}
