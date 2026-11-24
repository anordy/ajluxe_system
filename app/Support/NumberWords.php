<?php

namespace App\Support;

/** 364000 -> "Three Hundred Sixty-Four Thousand Shillings Only" (for invoices). */
class NumberWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    private const SCALES = ['', 'Thousand', 'Million', 'Billion', 'Trillion'];

    public static function shillings(float|int|string|null $value): string
    {
        $n = (int) round((float) $value);
        if ($n === 0) {
            return 'Zero Shillings Only';
        }

        return trim(($n < 0 ? 'Minus ' : '').self::words(abs($n))).' Shillings Only';
    }

    public static function words(int $n): string
    {
        $parts = [];
        $scale = 0;
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk) {
                $parts[] = trim(self::hundreds($chunk).' '.self::SCALES[$scale]);
            }
            $n = intdiv($n, 1000);
            $scale++;
        }

        return implode(' ', array_reverse($parts));
    }

    private static function hundreds(int $n): string
    {
        $out = [];
        if ($n >= 100) {
            $out[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $out[] = self::TENS[intdiv($n, 10)].($n % 10 ? '-'.self::ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $out[] = self::ONES[$n];
        }

        return implode(' ', $out);
    }
}
