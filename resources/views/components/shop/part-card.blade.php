@props(['part'])
@php
    $stock = $part->shopStock();
    $url = route('shop.product', [$part, $part->slug()]);
    $isPhoto = $part->imageUrl() && ! str_ends_with(strtolower((string) $part->image_path), '.svg');
    $line = $part->tyreLine() ?: ($part->specList()['Viscosity'] ?? null);
@endphp
{{-- one shelf tile: picture (heart; cart button on hover), brand, name, price, stock --}}
<article {{ $attributes->merge(['class' => 'group relative flex flex-col overflow-hidden rounded-xl border border-[#16181B]/10 bg-white transition-shadow hover:shadow-[0_10px_30px_rgba(22,24,27,.08)]']) }}>
    <div class="relative">
        <a href="{{ $url }}" wire:navigate class="relative block aspect-[4/3] overflow-hidden bg-gradient-to-b from-[#F7F8F9] to-[#ECEEF1]" tabindex="-1">
            <img src="{{ $part->thumbUrl() }}" loading="lazy" decoding="async" data-fallback="{{ $part->drawingUrl() }}" onerror="if(this.dataset.fallback&&this.src!==this.dataset.fallback){this.src=this.dataset.fallback;this.classList.add('p-4')}"
                 alt="" class="h-full w-full transition-transform duration-300 group-hover:scale-[1.04] {{ $isPhoto ? 'object-cover' : 'object-contain p-4' }}">
            @if ($part->hasDiscount())
                <span class="absolute left-3 top-3 rounded-full bg-brand-500 px-2.5 py-1 text-xs font-bold text-white">−{{ $part->discountShown() }}%</span>
            @endif
        </a>
        <x-shop.wish-button :part="$part" class="absolute right-3 top-3 shadow-sm" />
        {{-- on hover (always on touch screens): add to cart --}}
        @if ($stock > 0)
            <button type="button" wire:click="addToCart({{ $part->id }})" wire:loading.attr="disabled" wire:target="addToCart({{ $part->id }})"
                    class="absolute bottom-3 right-3 z-10 flex h-10 w-10 items-center justify-center rounded-md bg-[#16181B] text-white shadow-md transition duration-200 hover:bg-brand-500 [@media(hover:hover)]:translate-y-1 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:translate-y-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:focus-visible:translate-y-0 [@media(hover:hover)]:focus-visible:opacity-100"
                    aria-label="{{ __('Add :part to cart', ['part' => $part->name]) }}" title="{{ __('Add to cart') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/><path d="M13 9v4m-2-2h4"/></svg>
            </button>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-4">
        <p class="text-xs font-bold uppercase tracking-wide text-[#6B7280]">{{ $part->brand ?: ($part->subcategory ? __($part->subcategory) : ($part->category ? __($part->category) : __('Spare part'))) }}</p>
        <h3 class="mt-1 line-clamp-2 font-semibold leading-snug"><a href="{{ $url }}" wire:navigate class="after:absolute after:inset-0 hover:text-brand-600">{{ $part->name }}</a></h3>
        @if ($line) <p class="mt-1 line-clamp-1 text-[13px] text-[#4B5563]">{{ $line }}</p>
        @elseif ($fits = $part->fitsText(2)) <p class="mt-1 line-clamp-1 text-[13px] text-[#4B5563]">{{ __('Fits :cars', ['cars' => $fits]) }}</p>
        @endif
        <div class="mt-auto flex items-end justify-between gap-2 pt-4">
            <div class="min-w-0">
                @if ($part->hasDiscount()) <p class="text-[13px] text-[#6B7280] line-through">{{ money($part->selling_price) }}</p> @endif
                <p class="s-price whitespace-nowrap text-xl sm:text-[22px] {{ $part->hasDiscount() ? 'text-brand-600' : '' }}">{{ money($part->salePrice()) }}</p>
                @if ($stock <= 0) <p class="mt-1 text-[13px] font-semibold text-red-700">{{ __('Out of stock') }}</p> @endif
            </div>
        </div>
    </div>
</article>
