<?php

namespace App\Support;

class Phone
{
    /** "+255 754 111 222" / "255754111222" / "754111222" → "0754111222" (Tanzanian numbers). */
    public static function normalize(?string $p): string
    {
        $p = preg_replace('/[^0-9+]/', '', (string) $p);
        if (str_starts_with($p, '+255')) {
            $p = '0'.substr($p, 4);
        } elseif (str_starts_with($p, '255') && strlen($p) === 12) {
            $p = '0'.substr($p, 3);
        } elseif (strlen($p) === 9 && in_array($p[0], ['6', '7'], true)) {
            $p = '0'.$p;
        }

        return $p;
    }

    /** For wa.me links: 0754111222 → 255754111222 */
    public static function international(?string $p): string
    {
        $p = self::normalize($p);

        return str_starts_with($p, '0') ? '255'.substr($p, 1) : ltrim($p, '+');
    }
}
