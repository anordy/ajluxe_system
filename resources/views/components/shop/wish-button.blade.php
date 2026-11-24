@props(['part', 'size' => 'md'])
{{-- Heart: save / remove the part (the screen must use the AddsToCart trait). --}}
@php $on = \App\Support\Wishlist::has($part->id); @endphp
<button type="button" wire:click="toggleWishlist({{ $part->id }})" wire:loading.attr="disabled" wire:target="toggleWishlist({{ $part->id }})"
        aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $on ? __('Remove from wishlist') : __('Save to wishlist') }}" title="{{ $on ? __('Remove from wishlist') : __('Save to wishlist') }}"
        {{ $attributes->merge(['class' => 'z-10 flex shrink-0 items-center justify-center rounded-md border bg-white transition-colors '.($size === 'lg' ? 'h-12 w-12' : 'h-9 w-9').' '.($on ? 'border-brand-200 text-brand-500' : 'border-[#16181B]/10 text-[#16181B] hover:text-brand-500')]) }}>
    <svg class="{{ $size === 'lg' ? 'h-5 w-5' : 'h-[18px] w-[18px]' }}" viewBox="0 0 24 24" fill="{{ $on ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.4C1.5 7.6 3.8 4 7.4 4c2 0 3.6 1.1 4.6 2.7C13 5.1 14.6 4 16.6 4c3.6 0 5.9 3.6 4.7 7.1-1.8 4.8-9.3 9.4-9.3 9.4z"/></svg>
</button>
