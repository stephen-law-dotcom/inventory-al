<?php

namespace App\Support;

class Currency
{
    public static function format(float|int|string|null $amount, string $currencyCode = 'IDR'): string
    {
        $currencyCode = strtoupper($currencyCode ?: 'IDR');
        $decimals = $currencyCode === 'IDR' ? 0 : 2;
        $prefix = $currencyCode === 'IDR' ? 'Rp' : $currencyCode.' ';

        return $prefix.number_format((float) $amount, $decimals, ',', '.');
    }

    public static function toIdr(float|int|string|null $amount, float|int|string|null $exchangeRate): float
    {
        return round((float) $amount * max(0, (float) $exchangeRate), 2);
    }
}
