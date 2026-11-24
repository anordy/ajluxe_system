<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Staff pages exist only on the admin address (ADMIN_DOMAIN) when one is set. */
class StaffHost
{
    public function handle(Request $request, Closure $next)
    {
        $admin = config('ajluxe.admin_domain');
        if ($admin && strtolower($request->getHost()) !== $admin) {
            abort(404);
        }

        return $next($request);
    }
}
