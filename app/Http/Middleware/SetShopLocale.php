<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Website language: English (default) or Swahili, chosen in the language dropdown in the header.
 * Only the public website uses it; the staff portal stays in English.
 */
class SetShopLocale
{
    public const LOCALES = ['en' => 'English', 'sw' => 'Swahili'];

    public function handle(Request $request, Closure $next)
    {
        $lang = $request->session()->get('shop_locale', $request->cookie('shop_locale'));
        if (isset(self::LOCALES[$lang])) {
            app()->setLocale($lang);
        }

        return $next($request);
    }
}
