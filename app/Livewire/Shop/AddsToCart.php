<?php

namespace App\Livewire\Shop;

use App\Models\Part;
use App\Support\Cart;

/** "Add to cart" and the wishlist heart for any website screen. */
trait AddsToCart
{
    public function toggleWishlist(int $partId): void
    {
        $part = Part::online()->find($partId);
        if (! $part) {
            return;
        }
        $on = \App\Support\Wishlist::toggle($partId);
        $this->dispatch('toast', message: $on ? __(':part saved to your wishlist.', ['part' => $part->name]) : __(':part removed from your wishlist.', ['part' => $part->name]), type: 'success');
        $this->dispatch('wishlist-updated');
    }

    /** Put the part in the cart and go straight to checkout (log in first if needed). */
    public function buyNow(int $partId, int $qty = 1)
    {
        $part = Part::online()->find($partId);
        if (! $part || $part->shopStock() <= 0) {
            $this->dispatch('toast', message: __('Sorry, this part is out of stock.'), type: 'error');

            return null;
        }
        // keep what is already in the cart, but make sure at least this many of this part are in it
        $have = Cart::raw()[$partId] ?? 0;
        if ($have < $qty) {
            Cart::add($partId, $qty - $have);
        }
        $this->dispatch('cart-updated');

        return $this->redirect(route('shop.checkout'), navigate: true);
    }

    public function addToCart(int $partId, int $qty = 1): void
    {
        $part = Part::online()->find($partId);
        $before = Cart::raw()[$partId] ?? 0;
        $now = $part ? Cart::add($partId, $qty) : 0;

        if (! $part || $now === 0) {
            $this->dispatch('toast', message: __('Sorry, this part is out of stock.'), type: 'error');
        } elseif ($now === $before) {
            $this->dispatch('toast', message: __('You already have all :n we have in stock.', ['n' => $now]), type: 'error');
        } else {
            $this->dispatch('toast', message: __(':part added to your cart.', ['part' => $part->name]), type: 'success');
        }
        $this->dispatch('cart-updated');
    }
}
