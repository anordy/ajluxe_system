<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** The website's opening moment (config ajluxe.launch_at) and the staff preview key. */
class Launch
{
    public static function at(): ?CarbonImmutable
    {
        $v = trim((string) config('ajluxe.launch_at'));

        return $v === '' ? null : CarbonImmutable::parse($v, config('app.timezone'));
    }

    public static function pending(): bool
    {
        $at = self::at();

        return $at !== null && now()->lt($at);
    }

    /** Secret for ?preview=… so staff can see the real site before opening. */
    public static function previewKey(): string
    {
        return substr(hash_hmac('sha256', 'launch-preview', (string) config('app.key')), 0, 16);
    }

    public static function previewUrl(): string
    {
        $host = config('ajluxe.admin_domain') ? preg_replace('/^dashboard\./', '', config('ajluxe.admin_domain')) : null;
        $base = $host ? 'https://'.$host : url('/');

        return rtrim($base, '/').'/?preview='.self::previewKey();
    }
}
