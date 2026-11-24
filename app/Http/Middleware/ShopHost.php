<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** On the admin address the public website does not answer: "/" opens the dashboard, other shop pages are not found. */
class ShopHost
{
    public function handle(Request $request, Closure $next)
    {
        $admin = config('ajluxe.admin_domain');
        if ($admin && strtolower($request->getHost()) === $admin) {
            abort_unless($request->path() === '/', 404);

            return redirect('/dashboard');
        }

        return $next($request);
    }
}
