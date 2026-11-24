<?php

namespace App\Livewire\Shop;

use App\Support\Cart;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class CartPage extends Component
{
    public function setQty(int $partId, $qty): void
    {
        Cart::set($partId, (int) $qty);
        $this->dispatch('cart-updated');
    }

    public function remove(int $partId): void
    {
        Cart::remove($partId);
        $this->dispatch('cart-updated');
    }

    public function render()
    {
        $lines = Cart::lines();
        // stock went down since the part was added: lower the quantity to what we have
        $fixed = false;
        foreach ($lines as $l) {
            if ($l->part && $l->available > 0 && $l->qty > $l->available ) {
                Cart::set($l->part->id, $l->available);
                $fixed = true;
            }
        }
        if ($fixed) {
            $lines = Cart::lines();
            $this->dispatch('cart-updated');
        }

        return view('livewire.shop.cart', [
            'lines'   => $lines,
            'totals'  => Cart::totals($lines),
            'problem' => $lines->contains(fn ($l) => $l->problem),
        ])->title(__('Your cart'));
    }
}
