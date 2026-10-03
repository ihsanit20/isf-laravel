<?php

namespace App\Ledger;

/**
 * Ledger amounts are stored as integer paisa; source documents store taka.
 */
final class Money
{
    public static function toPaisa(int|float|string|null $taka): int
    {
        return (int) round(((float) $taka) * 100);
    }

    public static function toTaka(int $paisa): float
    {
        return round($paisa / 100, 2);
    }

    public static function format(int $paisa): string
    {
        return number_format($paisa / 100, 2, '.', '');
    }
}
