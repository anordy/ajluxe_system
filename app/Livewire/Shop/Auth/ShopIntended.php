<?php

namespace App\Livewire\Shop\Auth;

/** Where to send a customer after logging in: back to the shop page they came from (never a staff page). */
class ShopIntended
{
    public static function pull(): string
    {
        $url = (string) session()->pull('url.intended', '');
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        foreach (['/checkout', '/account', '/cart', '/shop'] as $ok) {
            if (str_starts_with($path, $ok)) {
                return $url;
            }
        }

        return route('shop.account');
    }
}
