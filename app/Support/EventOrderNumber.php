<?php

namespace App\Support;

use App\Models\EventOrder;
use App\Models\FundCycleEvent;

class EventOrderNumber
{
    /**
     * Format: FC{fund_cycle_id}E{event_id}EO-{seq}
     * Example: FC1E2EO-001 (cycle 1, event 2, 1st order for that event)
     */
    public static function generateForEvent(FundCycleEvent $event): string
    {
        $prefix = sprintf('FC%dE%dEO-', $event->fund_cycle_id, $event->id);

        $lastSeq = EventOrder::query()
            ->where('fund_cycle_event_id', $event->id)
            ->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->get(['order_number'])
            ->map(fn (EventOrder $order) => self::sequenceFrom($order->order_number, $prefix))
            ->filter()
            ->max();

        $nextSeq = ($lastSeq ?? 0) + 1;

        return $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    public static function prefixFor(FundCycleEvent $event): string
    {
        return sprintf('FC%dE%dEO-', $event->fund_cycle_id, $event->id);
    }

    private static function sequenceFrom(string $orderNumber, string $prefix): ?int
    {
        if (! str_starts_with($orderNumber, $prefix)) {
            return null;
        }

        $suffix = substr($orderNumber, strlen($prefix));

        if ($suffix === '' || ! ctype_digit($suffix)) {
            return null;
        }

        return (int) $suffix;
    }
}
