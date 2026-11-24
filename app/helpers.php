<?php

use App\Support\Money;
use App\Support\Settings;

if (! function_exists('setting')) {
    /** setting('business_name') */
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('webtext')) {
    /** A website text in the visitor's language: the "_sw" copy for Kiswahili when it is filled in, else English. */
    function webtext(string $key, mixed $default = null): mixed
    {
        if (app()->getLocale() === 'sw') {
            $sw = Settings::get($key.'_sw');
            if (is_string($sw) && trim($sw) !== '') {
                return $sw;
            }
        }

        return Settings::get($key, $default);
    }
}

if (! function_exists('opening_hours')) {
    /** Opening hours from Settings, with the day names in Kiswahili when the website is in Kiswahili. */
    function opening_hours(): string
    {
        $h = (string) Settings::get('opening_hours', '');
        if (app()->getLocale() !== 'sw' || $h === '') {
            return $h;
        }
        $days = ['Monday' => 'Jumatatu', 'Tuesday' => 'Jumanne', 'Wednesday' => 'Jumatano', 'Thursday' => 'Alhamisi', 'Friday' => 'Ijumaa',
            'Saturday' => 'Jumamosi', 'Sunday' => 'Jumapili', 'Mon' => 'Jumatatu', 'Tue' => 'Jumanne', 'Wed' => 'Jumatano', 'Thu' => 'Alhamisi',
            'Fri' => 'Ijumaa', 'Sat' => 'Jumamosi', 'Sun' => 'Jumapili', 'Closed' => 'Imefungwa', 'closed' => 'imefungwa'];

        return preg_replace_callback('/\b('.implode('|', array_keys($days)).')\b\.?/', fn ($m) => $days[$m[1]], $h);
    }
}

if (! function_exists('money')) {
    /** money(364000) -> "TZS 364,000" */
    function money(int|float|string|null $value): string
    {
        return Money::format($value);
    }
}

if (! function_exists('amount')) {
    /** amount(364000) -> "364,000" */
    function amount(int|float|string|null $value): string
    {
        return Money::amount($value);
    }
}

if (! function_exists('logo_url')) {
    /** Uploaded logo (Settings) or the bundled AJ LUXE AUTO logo. */
    function logo_url(): string
    {
        $path = Settings::get('logo_path');

        return $path ? asset('storage/'.$path) : asset('images/logo.png');
    }
}

if (! function_exists('list_options')) {
    /** list_options('payment_mode') -> ['Cash', 'M-Pesa', ...] */
    function list_options(string $list): array
    {
        return \App\Models\ListOption::valuesFor($list);
    }
}
