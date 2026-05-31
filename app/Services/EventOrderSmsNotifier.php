<?php

namespace App\Services;

use App\Models\EventOrder;
use App\Support\EventOrderTrackingUrl;

class EventOrderSmsNotifier
{
    public function __construct(
        private readonly SmsService $sms,
    ) {}

    public function send(EventOrder $order): bool
    {
        $total = (float) $order->total_amount;
        $advance = (float) $order->advance_amount;
        $due = max(0, round($total - $advance, 2));

        $message = sprintf(
            'Ihsan Shop: Order %s confirmed. Total BDT %s. Advance BDT %s. Due BDT %s. Track: %s',
            $order->order_number,
            self::formatMoney($total),
            self::formatMoney($advance),
            self::formatMoney($due),
            EventOrderTrackingUrl::short($order),
        );

        return $this->sms->send($order->customer_phone, $message, $order);
    }

    private static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
