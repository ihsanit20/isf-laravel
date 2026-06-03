<?php

namespace App\Support;

use App\Models\EventOrder;
use App\Models\EventPayment;

class EventOrderTrackingUrl
{
    public static function short(EventOrder $order): string
    {
        $token = EventOrder::ensureTrackingToken($order);

        return rtrim((string) config('services.frontend.url'), '/').'/t/'.$token;
    }

    public static function paymentReceiptUrl(
        EventOrder $order,
        EventPayment $payment,
        ?string $token = null,
        ?string $customerPhone = null,
    ): string {
        $baseUrl = route('public.orders.payments.receipt', [
            'orderNumber' => $order->order_number,
            'payment' => $payment->id,
        ]);

        if ($token !== null && $token !== '') {
            return $baseUrl.'?'.http_build_query(['token' => $token]);
        }

        if ($customerPhone !== null && $customerPhone !== '') {
            return $baseUrl.'?'.http_build_query(['customer_phone' => $customerPhone]);
        }

        return $baseUrl;
    }
}
