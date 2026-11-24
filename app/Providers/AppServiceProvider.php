<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // website components keep the visitor's language on their background updates
        \Livewire\Livewire::addPersistentMiddleware([\App\Http\Middleware\SetShopLocale::class, \App\Http\Middleware\StaffHost::class, \App\Http\Middleware\ShopHost::class]);
    }
}
