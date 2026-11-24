<?php

use App\Http\Controllers\ImportUploadController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ReportExportController;
use App\Livewire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AJ LUXE AUTO — web routes
|--------------------------------------------------------------------------
| Roles: admin (everything), manager (operations, no users/settings),
|        mechanic (garage jobs), staff (parts counter / POS).
*/

/*
| Public website (online shop) — customers use the separate "shop" login
*/
// Pictures (storage/app/public). Normally the web server sends these itself through the
// public/storage link; this only runs when that link is missing (e.g. never created on Windows).
Route::get('/storage/{path}', function (string $path) {
    $root = realpath(storage_path('app/public'));
    $file = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)
        && preg_match('/\.(jpe?g|png|webp|gif|svg)$/i', $file), 404);

    return response()->file($file, ['Cache-Control' => 'public, max-age=604800']);
})->where('path', '.*')->name('storage.public');

Route::name('shop.')->middleware(['shop.host', 'shop.locale'])->group(function () {
    // English / Kiswahili switch in the header; remembered for a year on this device
    Route::get('/language/{lang}', function (Request $request, string $lang) {
        abort_unless(isset(\App\Http\Middleware\SetShopLocale::LOCALES[$lang]), 404);
        $request->session()->put('shop_locale', $lang);
        $back = url()->previous();

        return redirect(str_starts_with($back, url('/')) ? $back : route('shop.home'))->withCookie(cookie()->forever('shop_locale', $lang));
    })->name('language');

    Route::get('/', Livewire\Shop\Home::class)->name('home');
    Route::get('/shop', Livewire\Shop\Catalog::class)->name('catalog');
    Route::get('/shop/{part}/{slug?}', Livewire\Shop\Product::class)->whereNumber('part')->name('product');
    Route::get('/cart', Livewire\Shop\CartPage::class)->name('cart');
    Route::get('/wishlist', Livewire\Shop\WishlistPage::class)->name('wishlist');
    Route::get('/garage', Livewire\Shop\Garage::class)->name('garage');
    Route::get('/import-for-you', Livewire\Shop\ImportOrder::class)->name('import');

    Route::middleware('guest:shop')->group(function () {
        Route::get('/account/login', Livewire\Shop\Auth\Login::class)->name('login');
        Route::get('/account/register', Livewire\Shop\Auth\Register::class)->name('register');
        // Continue with Google
        Route::get('/account/google', [\App\Http\Controllers\ShopGoogleController::class, 'redirect'])->name('google');
        Route::get('/account/google/callback', [\App\Http\Controllers\ShopGoogleController::class, 'callback'])->name('google.callback');
        Route::get('/account/google/finish', Livewire\Shop\Auth\GoogleFinish::class)->name('google.finish');
    });
    Route::middleware('auth:shop')->group(function () {
        Route::get('/checkout', Livewire\Shop\Checkout::class)->name('checkout');
        Route::get('/account', Livewire\Shop\Account::class)->name('account');
        Route::get('/account/orders/{order}', Livewire\Shop\OrderShow::class)->name('order');
        Route::post('/account/logout', function (Request $request) {
            Auth::guard('shop')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('shop.home');
        })->name('logout');
    });
});

/*
| Management system (staff)
*/
Route::middleware(['staff.host', 'guest'])->group(function () {
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
});

Route::middleware(['staff.host', 'auth:web'])->group(function () {   // staff login only — website customers can never pass
    Route::post('/logout', function (Request $request) {
        \App\Support\Audit::log('Auth', 'LOGOUT', $request->user()->username);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::middleware('account')->group(function () {
        Route::get('/password', Livewire\Profile\ChangePassword::class)->name('password.change');

        Route::get('/dashboard', Livewire\Dashboard::class)->name('dashboard');

        // ---- Administration ----
        Route::middleware('role:admin')->group(function () {
            Route::get('/users', Livewire\Users\Index::class)->name('users.index');
            Route::get('/settings', Livewire\Settings\Edit::class)->name('settings');
            Route::get('/audit-log', Livewire\AuditLogIndex::class)->name('audit.index');
        });
        // pictures chosen in a form are sent once, straight away (see <x-photo-input>)
        Route::post('/pictures', \App\Http\Controllers\PictureUploadController::class)->middleware(['role:admin,manager,staff', 'throttle:60,1'])->name('pictures.upload');
        Route::get('/lists', Livewire\Lists\Index::class)->middleware('role:admin,manager')->name('lists.index');

        // ---- Master data (Phase 2) ----
        Route::middleware('role:admin,manager,staff')->group(function () {
            Route::get('/customers', Livewire\Customers\Index::class)->name('customers.index');
            Route::get('/customers/{customer}', Livewire\Customers\Show::class)->name('customers.show');
        });
        Route::get('/vehicles', Livewire\Vehicles\Index::class)->middleware('role:admin,manager,staff,mechanic')->name('vehicles.index');
        Route::get('/parts', Livewire\Parts\Index::class)->middleware('role:admin,manager,staff,mechanic')->name('parts.index');
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/suppliers', Livewire\Suppliers\Index::class)->name('suppliers.index');
            Route::get('/services', Livewire\Services\Index::class)->name('services.index');
            Route::get('/mechanics', Livewire\Mechanics\Index::class)->name('mechanics.index');
        });

        // ---- Garage: job cards (Phase 3) ----
        Route::middleware('role:admin,manager,mechanic')->group(function () {
            Route::get('/job-cards', Livewire\JobCards\Index::class)->name('job-cards.index');
            Route::get('/job-cards/new', Livewire\JobCards\Create::class)->middleware('role:admin,manager')->name('job-cards.create');
            Route::get('/job-cards/{jobCard}', Livewire\JobCards\Show::class)->name('job-cards.show');
        });
        Route::get('/invoices', Livewire\Invoices\Index::class)->middleware('role:admin,manager,staff')->name('invoices.index');

        // ---- Parts shop (Phase 4) ----
        Route::get('/pos', Livewire\Pos\Index::class)->middleware('role:admin,manager,staff')->name('pos');
        Route::get('/orders', Livewire\Orders\Index::class)->middleware('role:admin,manager,staff')->name('orders.index');
        Route::get('/stock', Livewire\Stock\Index::class)->middleware('role:admin,manager,staff')->name('stock.index');
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/purchase-orders', Livewire\PurchaseOrders\Index::class)->name('purchase-orders.index');
            Route::get('/purchase-orders/new', Livewire\PurchaseOrders\Form::class)->name('purchase-orders.create');
            Route::get('/purchase-orders/{purchaseOrder}/edit', Livewire\PurchaseOrders\Form::class)->name('purchase-orders.edit');
            Route::get('/purchase-orders/{purchaseOrder}', Livewire\PurchaseOrders\Show::class)->name('purchase-orders.show');
            Route::get('/returns', Livewire\Returns\Index::class)->name('returns.index');
        });

        Route::get('/import-orders', Livewire\Imports\Index::class)->middleware('role:admin,manager,staff')->name('imports.index');

        // ---- Website content ----
        Route::get('/website/car-requests', Livewire\Website\CarRequests::class)->middleware('role:admin,manager,staff')->name('website.requests');
        Route::middleware('role:admin,manager')->prefix('website')->name('website.')->group(function () {
            Route::get('/banners', Livewire\Website\Banners::class)->name('banners');
            Route::get('/cars', Livewire\Website\Cars::class)->name('cars');
            Route::post('/cars/import', \App\Http\Controllers\VehicleImportController::class)->name('cars.import');
            Route::get('/texts', Livewire\Website\Texts::class)->name('texts');
        });

        // ---- Print pages (role checks inside the controller) ----
        Route::prefix('print')->name('print.')->controller(PrintController::class)->group(function () {
            Route::get('/job-card/{jobCard}', 'jobCard')->name('job-card');
            Route::get('/proforma/{jobCard}', 'proforma')->name('proforma');
            Route::get('/invoice/{invoice}', 'invoice')->name('invoice');
            Route::get('/receipt/{payment}', 'receipt')->name('receipt');
            Route::get('/sale/{invoice}', 'thermal')->name('thermal');
            Route::get('/purchase-order/{purchaseOrder}', 'purchaseOrder')->name('purchase-order');
            Route::get('/return/{stockReturn}', 'stockReturn')->name('return');
        });

        // ---- Reports & import (Phase 5) ----
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/reports', Livewire\Reports\Index::class)->name('reports.index');
            Route::get('/reports/export/{report}', [ReportExportController::class, '__invoke'])->name('reports.export');
        });
        Route::get('/import', Livewire\Import\Index::class)->middleware('role:admin')->name('import.index');
        Route::post('/import/upload', ImportUploadController::class)->middleware('role:admin')->name('import.upload');
        Route::get('/import/template/{type}', [ReportExportController::class, 'template'])->middleware('role:admin')->name('import.template');
    });
});
