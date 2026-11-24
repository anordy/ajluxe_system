<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'    => \App\Http\Middleware\EnsureRole::class,
            'account' => \App\Http\Middleware\EnsureAccountUsable::class,
            'shop.locale' => \App\Http\Middleware\SetShopLocale::class,
        ]);
        $middleware->encryptCookies(except: ['shop_locale']);
        // website customers and staff each go to their own login / home
        $middleware->redirectGuestsTo(fn (Request $r) => $r->routeIs('shop.*') ? route('shop.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $r) => $r->routeIs('shop.*') ? route('shop.account') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
