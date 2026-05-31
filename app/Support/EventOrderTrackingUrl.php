<?php

namespace App\Support;

use App\Models\EventOrder;

class EventOrderTrackingUrl
{
    public static function short(EventOrder $order): string
    {
        $token = EventOrder::ensureTrackingToken($order);

        return rtrim((string) config('services.frontend.url'), '/').'/t/'.$token;
    }
}
