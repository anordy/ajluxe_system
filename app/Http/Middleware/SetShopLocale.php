<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Website language: English (default) or Kiswahili, chosen with the switch in the header.
 * Only the public website uses it; the staff portal stays in English.
 */
class SetShopLocale
{
    public const LOCALES = ['en' => 'English', 'sw' => 'Kiswahili'];

    public function handle(Request $request, Closure $next)
    {
        $lang = $request->session()->get('shop_locale', $request->cookie('shop_locale'));
        if (isset(self::LOCALES[$lang])) {
            app()->setLocale($lang);
        }

        return $next($request);
    }
}
