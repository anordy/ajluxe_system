@php $stock = (int) floor(max(0, (float) $p->stock_qty)); @endphp
<div class="wrap pt-8">
    <nav class="text-sm text-[#6B7280]" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ route('shop.catalog') }}" wire:navigate class="hover:text-[#16181B]">{{ __('Shop') }}</a>
        @if ($p->category) <span aria-hidden="true"> / </span><a href="{{ route('shop.catalog', ['category' => $p->category]) }}" wire:navigate class="hover:text-[#16181B]">{{ __($p->category) }}</a> @endif
        @if ($p->subcategory) <span aria-hidden="true"> / </span><a href="{{ route('shop.catalog', ['category' => $p->category, 'type' => $p->subcategory]) }}" wire:navigate class="hover:text-[#16181B]">{{ __($p->subcategory) }}</a> @endif
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        @php $pic = $p->imageUrl() ?: \App\Support\ProductArt::dataUri(...\App\Support\ProductArt::forCategory($p->category, $p->subcategory, $p->name)); @endphp
        {{-- picture: zooms in where the mouse is; full-screen view with the button or a tap --}}
        <div x-data="{ zoom: false, x: 50, y: 50, big: false,
                    move(e) { const r = e.currentTarget.getBoundingClientRect(); this.x = (e.clientX - r.left) / r.width * 100; this.y = (e.clientY - r.top) / r.height * 100 } }"
             class="relative aspect-square overflow-hidden rounded-xl border border-[#16181B]/10 bg-white">
            <div class="h-full w-full [@media(hover:hover)]:cursor-zoom-in" @mouseenter="zoom = true" @mouseleave="zoom = false" @mousemove="move($event)" @click="big = true">
                <img src="{{ $pic }}" alt="{{ $p->name }}" data-fallback="{{ $p->drawingUrl() }}" onerror="if(this.dataset.fallback&&this.src!==this.dataset.fallback){this.src=this.dataset.fallback}"
                     class="h-full w-full object-contain p-6 transition-transform duration-150 ease-out motion-reduce:transition-none"
                     :style="zoom && window.matchMedia('(hover: hover)').matches ? `transform: scale(2.2); transform-origin: ${x}% ${y}%` : ''">
            </div>
            @if ($p->hasDiscount()) <span class="tag pointer-events-none absolute left-0 top-5 py-1.5 pl-5 pr-3 text-lg">{{ __('Save :n%', ['n' => $p->discountShown()]) }}</span> @endif
            <button type="button" @click="big = true" class="absolute bottom-4 left-4 flex h-11 w-11 items-center justify-center rounded-md bg-white shadow-md ring-1 ring-[#16181B]/10 hover:bg-[#F4F5F7]"
                    aria-label="{{ __('View full screen') }}" title="{{ __('View full screen') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
            </button>
            <template x-teleport="body">
                <div x-show="big" x-cloak x-transition.opacity @keydown.escape.window="big = false" @click.self="big = false"
                     class="fixed inset-0 z-[80] flex items-center justify-center bg-[#16181B]/90 p-4" role="dialog" aria-modal="true" aria-label="{{ $p->name }}">
                    <img src="{{ $pic }}" alt="{{ $p->name }}" class="max-h-full max-w-full rounded-lg bg-white object-contain p-4">
                    <button type="button" @click="big = false" x-init="$watch('big', v => v && $nextTick(() => $el.focus()))"
                            class="absolute right-4 top-4 flex h-11 w-11 items-center justify-center rounded-full bg-white text-[#16181B] hover:bg-[#F4F5F7]" aria-label="{{ __('Close') }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
            </template>
        </div>

        <div>
            <p class="text-[#6B7280]">{{ $p->brand ?: $p->category }}</p>
            <h1 class="mt-1 font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">{{ $p->name }}</h1>
            @if ($line = $p->tyreLine()) <p class="mt-2 text-lg text-[#374151]">{{ $line }}</p> @endif

            <div class="mt-6 rounded-xl bg-[#F7F8F9] p-5" x-data="{ qty: $wire.entangle('qty'), p: {{ (float) $p->salePrice() }}, n: {{ $p->hasDiscount() ? (float) $p->selling_price : 0 }}, max: {{ $stock }},
                    tzs(v) { return @js(setting('currency', 'TZS')) + ' ' + Math.round(v).toLocaleString('en-US') } }">
                @if ($stock <= 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200">{{ __('Out of stock') }}</span>
                @endif
                <div class="{{ $stock <= 0 ? 'mt-3' : '' }} flex flex-wrap items-baseline gap-x-3">
                    <span class="s-price text-5xl {{ $p->hasDiscount() ? 'text-brand-600' : '' }}" x-text="tzs(p * qty)">{{ money($p->salePrice() * $qty) }}</span>
                    @if ($p->hasDiscount()) <span class="text-lg text-[#6B7280] line-through" x-text="tzs(n * qty)">{{ money($p->selling_price * $qty) }}</span> @endif
                </div>
                <p class="mt-1 text-[#6B7280]" x-text="qty > 1 ? qty + ' × ' + tzs(p) : {{ Js::from(__('per').' '.__($p->tyreLine() ? 'tyre' : $p->unitLabel())) }}">{{ $qty > 1 ? $qty.' × '.money($p->salePrice()) : __('per').' '.__($p->tyreLine() ? 'tyre' : $p->unitLabel()) }}</p>

                @if ($family)
                    <div class="mt-4">
                        <p class="s-label">{{ __('Pack size') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($family as $v)
                                <a href="{{ route('shop.product', [$v, $v->slug()]) }}" wire:navigate @if ($v->id === $p->id) aria-current="true" @endif
                                   class="min-w-[72px] rounded-lg border bg-white px-4 py-2.5 text-center font-display text-xl font-bold leading-none transition-colors {{ $v->id === $p->id ? 'border-[#16181B] ring-1 ring-[#16181B]' : 'border-[#16181B]/15 hover:border-[#16181B]/40' }}">{{ $v->packLabel() }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($stock > 0)
                    <form wire:submit="add" class="mt-5 flex max-w-md gap-2">
                        <div class="flex items-center rounded-md border border-[#16181B]/15 bg-white" role="group" aria-label="{{ __('Quantity') }}">
                            <button type="button" @click="qty = Math.max(1, qty - 1)" :disabled="qty <= 1" class="flex h-12 w-11 items-center justify-center rounded-md text-xl hover:bg-[#F1F2F4] disabled:text-[#D1D5DB]" aria-label="{{ __('One less') }}">−</button>
                            <span class="w-8 text-center text-lg font-semibold" x-text="qty">{{ $qty }}</span>
                            <button type="button" @click="qty = Math.min(max, qty + 1)" :disabled="qty >= max" class="flex h-12 w-11 items-center justify-center rounded-md text-xl hover:bg-[#F1F2F4] disabled:text-[#D1D5DB]" aria-label="{{ __('One more') }}">+</button>
                        </div>
                        <button type="submit" title="{{ __('Add to cart') }}" aria-label="{{ __('Add to cart') }}" wire:loading.attr="disabled" wire:target="add"
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-[#16181B]/15 bg-white transition-colors hover:border-[#16181B] hover:bg-[#16181B] hover:text-white">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/><path d="M13 9v4m-2-2h4"/></svg>
                        </button>
                        <button type="button" wire:click="buy" class="s-btn-red flex-1 rounded-md" wire:loading.attr="disabled" wire:target="buy">{{ __('Buy now') }}</button>
                        <x-shop.wish-button :part="$p" size="lg" />
                    </form>
                @else
                    <div class="mt-5 flex gap-2">
                        <button type="button" class="s-btn-line flex-1 rounded-md" @click="Livewire.dispatch('open-car-request', { parts: @js($p->name) })">{{ __('Ask us to get it') }}</button>
                        <x-shop.wish-button :part="$p" size="lg" />
                    </div>
                @endif
            </div>

            <dl class="mt-8 divide-y divide-[#16181B]/10 border-y border-[#16181B]/10 text-[15px]">
                @foreach (array_filter(array_merge([
                    'Fits' => $p->fitsText(50),
                    'Brand' => $p->brand,
                    'Type' => $p->subcategory,
                ], array_diff_key($p->specList(), ['Fitting position' => 1, 'Sold per' => 1]), [
                    'Part number' => $p->oem_no ?: ($p->specList()['Part number'] ?? null),
                    'Our code' => $p->sku,
                    'Sold per' => $p->unit,
                ])) as $k => $v)
                    <div class="flex justify-between gap-6 py-3"><dt class="text-[#6B7280]">{{ __($k) }}</dt><dd class="text-right font-medium">{{ \App\Models\Part::specDisplay($k, (string) $v) }}</dd></div>
                @endforeach
            </dl>

            @if ($p->web_description)
                <div class="mt-6 max-w-prose whitespace-pre-line leading-relaxed text-[#374151]">{{ $p->web_description }}</div>
            @endif

            <p class="mt-6 text-sm text-[#4B5563]">{{ __('Not sure it fits? Send us your car model on WhatsApp before you order, or let our garage fit it for you.') }}</p>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="pt-16">
            <h2 class="mb-6 font-display text-3xl font-extrabold tracking-tight">{{ __('You may also need') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                @foreach ($related as $r) <x-shop.part-card :part="$r" wire:key="r{{ $r->id }}" /> @endforeach
            </div>
        </section>
    @endif
</div>
