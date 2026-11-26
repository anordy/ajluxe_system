<?php

namespace App\Http\Middleware;

use App\Support\Launch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Before the opening time the public website shows only the countdown page. */
class ComingSoon
{
    private const COOKIE = 'ajl_preview';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Launch::pending()) {
            return $next($request);
        }
        $key = Launch::previewKey();
        if (hash_equals($key, (string) $request->query('preview', ''))) {
            // staff preview: remember it in this browser until the site opens
            return redirect($request->url())->withCookie(cookie(self::COOKIE, $key, 60 * 24 * 30, null, null, $request->secure(), true, false, 'lax'));
        }
        if (hash_equals($key, (string) $request->cookie(self::COOKIE, ''))) {
            return $next($request);
        }

        return response()->view('coming-soon', ['launch' => Launch::at()], 200)
            ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }
}
