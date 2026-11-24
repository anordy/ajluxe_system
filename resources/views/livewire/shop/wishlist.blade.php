<div class="wrap py-10">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-4xl font-extrabold tracking-tight">{{ __('Wishlist') }}</h1>
            <p class="mt-1 text-[#4B5563]">
                @if ($parts->isNotEmpty()) {{ trans_choice(':count part|:count parts', $parts->count()) }} · @endif
                @guest('shop') {{ __('Log in to keep your wishlist on every device.') }} @else {{ __('Saved in your account.') }} @endguest
            </p>
        </div>
        @guest('shop') <a href="{{ route('shop.login') }}" wire:navigate class="s-btn-line rounded-md">{{ __('Log in') }}</a> @endguest
    </div>

    @if ($parts->isEmpty())
        <div class="mt-10 rounded-xl border border-dashed border-[#16181B]/20 p-12 text-center">
            <svg class="mx-auto h-10 w-10 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.4C1.5 7.6 3.8 4 7.4 4c2 0 3.6 1.1 4.6 2.7C13 5.1 14.6 4 16.6 4c3.6 0 5.9 3.6 4.7 7.1-1.8 4.8-9.3 9.4-9.3 9.4z"/></svg>
            <p class="mt-3 font-semibold">{{ __('Your wishlist is empty') }}</p>
            <p class="mt-1 text-sm text-[#6B7280]">{{ __('Tap the heart on any part to save it here for later.') }}</p>
            <a href="{{ route('shop.catalog') }}" wire:navigate class="s-btn-red mt-6 rounded-md">{{ __('Shop parts') }}</a>
        </div>
    @else
        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($parts as $p) <x-shop.part-card :part="$p" wire:key="w{{ $p->id }}" /> @endforeach
        </div>
    @endif
</div>
