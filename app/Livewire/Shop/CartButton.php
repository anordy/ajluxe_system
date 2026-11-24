<?php

namespace App\Livewire\Shop;

use App\Support\Cart;
use Livewire\Attributes\On;
use Livewire\Component;

class CartButton extends Component
{
    public int $count = 0;
    public float $total = 0;

    #[On('cart-updated')]
    public function refresh(): void {}

    public function render()
    {
        $this->count = Cart::count();
        $this->total = $this->count ? (float) Cart::totals()['total'] : 0;

        return <<<'BLADE'
            <a href="{{ route('shop.cart') }}" wire:navigate class="relative flex items-center gap-2 rounded-md px-2 py-2 hover:bg-white/10" aria-label="{{ __('Cart') }}, {{ trans_choice(':count item|:count items', $count) }}">
                <span class="relative"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>
                @if ($count)
                    <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-500 px-1 text-[11px] font-bold text-white">{{ $count > 99 ? '99+' : $count }}</span>
                @endif</span>
                <span class="hidden text-left text-sm leading-tight lg:block"><span class="block text-white/60">{{ __('Cart') }}</span><span class="font-semibold">{{ $count ? money($total) : __('Empty') }}</span></span>
            </a>
        BLADE;
    }
}
