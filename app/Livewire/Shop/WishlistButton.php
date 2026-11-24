<?php

namespace App\Livewire\Shop;

use App\Support\Wishlist;
use Livewire\Attributes\On;
use Livewire\Component;

/** Heart in the website header with the number of saved parts. */
class WishlistButton extends Component
{
    public int $n = 0;

    #[On('wishlist-updated')]
    public function refresh(): void {}

    public function render()
    {
        $this->n = Wishlist::count();

        return <<<'BLADE'
            <a href="{{ route('shop.wishlist') }}" wire:navigate class="relative flex items-center rounded-md p-2 hover:bg-white/10"
               aria-label="{{ __('Wishlist') }}, {{ trans_choice(':count item|:count items', $n) }}" title="{{ __('Wishlist') }}">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.4C1.5 7.6 3.8 4 7.4 4c2 0 3.6 1.1 4.6 2.7C13 5.1 14.6 4 16.6 4c3.6 0 5.9 3.6 4.7 7.1-1.8 4.8-9.3 9.4-9.3 9.4z"/></svg>
                @if ($n) <span class="absolute right-0.5 top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-brand-500 px-1 text-[11px] font-bold text-white">{{ $n }}</span> @endif
            </a>
        BLADE;
    }
}
