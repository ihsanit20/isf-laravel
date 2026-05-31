<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Models\EventOrder;
use App\Models\EventOrderStatusHistory;
use Illuminate\Support\Facades\DB;

class EventOrderConfirmationService
{
    public function __construct(
        private readonly EventOrderSmsNotifier $smsNotifier,
    ) {}

    public function confirm(EventOrder $order, string $historyNote): bool
    {
        if (! $this->markConfirmed($order, $historyNote)) {
            return false;
        }

        $order->refresh();
        $this->smsNotifier->send($order);

        return true;
    }

    public function markConfirmed(EventOrder $order, string $historyNote): bool
    {
        if ($order->status === EventOrderStatus::Confirmed) {
            return false;
        }

        $confirmed = false;

        DB::transaction(function () use ($order, $historyNote, &$confirmed): void {
            $order->refresh();

            if ($order->status === EventOrderStatus::Confirmed) {
                return;
            }

            $now = now();

            $order->update([
                'status' => EventOrderStatus::Confirmed,
                'confirmed_at' => $now,
            ]);

            EventOrder::ensureTrackingToken($order);

            EventOrderStatusHistory::create([
                'event_order_id' => $order->id,
                'status' => EventOrderStatus::Confirmed->value,
                'note' => $historyNote,
                'changed_by_user_id' => null,
                'changed_at' => $now,
            ]);

            $confirmed = true;
        });

        return $confirmed;
    }

    public function sendConfirmationSms(EventOrder $order): void
    {
        if ($order->status !== EventOrderStatus::Confirmed) {
            return;
        }

        $this->smsNotifier->send($order);
    }
}
