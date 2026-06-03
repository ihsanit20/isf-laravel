<?php

namespace App\Enums;

enum EventPaymentType: string
{
    case Advance = 'advance';
    case Due = 'due';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Advance => 'Advance',
            self::Due => 'Due',
            self::Manual => 'Manual',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
