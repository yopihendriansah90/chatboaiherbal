<?php

namespace App\Support;

final class CurrencyFormatter
{
    public static function usd(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return '$'.number_format((float) $value, 10, '.', ',');
    }

    public static function idr(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return 'Rp'.number_format((float) $value, 4, ',', '.');
    }
}
