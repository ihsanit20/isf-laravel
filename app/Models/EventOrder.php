<?php

namespace App\Models;

use App\Enums\EventOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'fund_cycle_event_id',
    'event_pickup_point_id',
    'order_number',
    'tracking_token',
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
            'status' => EventOrderStatus::class,
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

    public function smsLogs(): MorphMany
    {
        return $this->morphMany(SmsLog::class, 'smsable');
    }

    public static function ensureTrackingToken(EventOrder $order): string
    {
        if ($order->tracking_token) {
            return $order->tracking_token;
        }

        do {
            $token = Str::lower(Str::random(10));
        } while (static::query()->where('tracking_token', $token)->exists());

        $order->update(['tracking_token' => $token]);

        return $token;
    }

    public static function verifiedPaidSubquery(): string
    {
        return "(SELECT COALESCE(SUM(amount), 0) FROM event_payments WHERE event_order_id = event_orders.id AND payment_status = 'verified')";
    }

    public static function dueAmountSqlExpression(): string
    {
        $subquery = self::verifiedPaidSubquery();

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "MAX(0, event_orders.total_amount - {$subquery})",
            default => "GREATEST(0, event_orders.total_amount - {$subquery})",
        };
    }

    public function totalVerifiedPaid(): float
    {
        return round((float) $this->payments()
            ->where('payment_status', 'verified')
            ->sum('amount'), 2);
    }

    public function dueAmount(): float
    {
        return max(0, round((float) $this->total_amount - $this->totalVerifiedPaid(), 2));
    }

    public function hasVerifiedAdvancePayment(): bool
    {
        return $this->payments()
            ->where('payment_status', 'verified')
            ->where(function ($query): void {
                $query
                    ->where('payment_type', 'advance')
                    ->orWhereNull('payment_type');
            })
            ->exists();
    }

    /**
     * @return 'none'|'verified'|'pending'|'unpaid'
     */
    public function advancePaymentStatus(): string
    {
        if ((float) $this->advance_amount <= 0) {
            return 'none';
        }

        if ($this->hasVerifiedAdvancePayment()) {
            return 'verified';
        }

        if ($this->payments()->where('payment_status', 'pending')->exists()) {
            return 'pending';
        }

        return 'unpaid';
    }

    public function isAdvancePaid(): bool
    {
        if ((float) $this->advance_amount <= 0) {
            return true;
        }

        return $this->hasVerifiedAdvancePayment();
    }
}
