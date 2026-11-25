<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard by permission (any of).   ->middleware('perm:pos,sales.view') */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user()?->allows(...$permissions)) {
            abort(403, 'You do not have permission to open this page.');
        }

        return $next($request);
    }
}
