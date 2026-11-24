<?php

namespace App\Livewire\Shop;

use App\Models\Part;
use App\Support\Wishlist;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.shop')]
class WishlistPage extends Component
{
    use AddsToCart;

    #[On('wishlist-updated')]
    public function refresh(): void {}

    public function render()
    {
        $ids = Wishlist::ids();
        $parts = Part::online()->whereIn('id', $ids)->with('fitments.carModel')->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids))->values();

        return view('livewire.shop.wishlist', ['parts' => $parts])->title(__('Wishlist'));
    }
}
