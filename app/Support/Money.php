<?php

namespace App\Support;

/**
 * Money formatting for the whole app: "TZS 364,000" (currency + space + #,##0, no decimals).
 */
class Money
{
    /** 364000 -> "364,000" */
    public static function amount(int|float|string|null $value): string
    {
        return number_format(round((float) $value), 0, '.', ',');
    }

    /** 364000 -> "TZS 364,000"   |   -1500 -> "-TZS 1,500" */
    public static function format(int|float|string|null $value, ?string $currency = null): string
    {
        $n = (float) $value;
        $currency ??= Settings::get('currency', 'TZS');

        return ($n < 0 ? '-' : '').$currency.' '.self::amount(abs($n));
    }
}
