@props(['part', 'family' => null])
@php
    $stock = $part->shopStock();
    $url = route('shop.product', [$part, $part->slug()]);
    $specs = $part->specList();
    $sized = $family && $family->count() > 1;
    $tyreLine = $part->tyreLine();
    $isTyre = (bool) $tyreLine;

    // one short line under the name, e.g. "M 14 × 1.25, Spanner size: 16 mm" or "Summer tyres 205/55 R16 91V"
    $summaryKeys = array_diff(['Thread size', 'Spanner size', 'Size', 'Viscosity', 'Capacity', 'Battery capacity', 'Bulb type', 'Length', 'Diameter'], $sized ? ['Capacity'] : []);
    $summary = $tyreLine ?: collect($summaryKeys)->filter(fn ($k) => isset($specs[$k]))->take(2)
        ->map(fn ($k) => in_array($k, ['Thread size', 'Size', 'Viscosity', 'Bulb type'], true) ? $specs[$k] : $k.': '.$specs[$k])->implode(', ');

    // key specifications first; the full list opens on request
    $hide = array_merge(['Fitting position', 'Sold per'], $sized ? ['Capacity'] : []);
    $main = array_diff_key($specs, array_flip([...$hide, 'Part number']));
    $extra = array_filter(['Part number' => $specs['Part number'] ?? $part->oem_no, 'Item number' => $part->sku, 'Manufacturer' => $part->brand], fn ($v) => filled($v));
    $rows = $main + $extra;
    $shown = 6;

    // the price panel: one entry per pack size (or just this part)
    $opts = collect($sized ? $family : [$part])->mapWithKeys(fn ($v) => [$v->id => [
        'name'   => $v->name,
        'url'    => route('shop.product', [$v, $v->slug()]),
        'p'      => (float) $v->salePrice(),
        'n'      => $v->hasDiscount() ? (float) $v->selling_price : 0,
        'disc'   => $v->hasDiscount() ? $v->discountShown() : 0,
        'unit'   => __($isTyre ? 'tyre' : $v->unitLabel()),
        'stock'  => $v->shopStock(),
        'img'    => $v->thumbUrl(),
    ]])->all();
    $startQty = $isTyre ? max(1, min(4, $stock)) : 1;
    $cur = setting('currency', 'TZS');
    $isPhoto = $part->imageUrl() && ! str_ends_with(strtolower((string) $part->image_path), '.svg');
@endphp
{{-- one result: picture · name and key specifications · price panel --}}
<article x-data="{ sel: {{ $part->id }}, qty: {{ $startQty }}, all: false, v: @js($opts),
            tzs(n) { return @js($cur) + ' ' + Math.round(n).toLocaleString('en-US') },
            get it() { return this.v[this.sel] } }"
         {{ $attributes->merge(['class' => 'grid gap-5 rounded-xl border border-[#16181B]/10 bg-white p-4 transition-shadow hover:shadow-[0_10px_30px_rgba(22,24,27,.07)] sm:grid-cols-[160px_1fr] sm:p-5 lg:grid-cols-[180px_1fr_280px]']) }}>

    {{-- picture --}}
    <div class="relative mx-auto w-full max-w-[220px] sm:mx-0 sm:max-w-none">
    <a href="{{ $url }}" :href="v[sel].url" wire:navigate class="relative block aspect-square w-full overflow-hidden rounded-lg sm:max-w-none {{ $isPhoto ? 'bg-white ring-1 ring-[#16181B]/[0.06]' : 'bg-gradient-to-b from-[#F7F8F9] to-[#ECEEF1]' }}">
        <img src="{{ $part->thumbUrl() }}" loading="lazy" decoding="async" :src="it.img" data-fallback="{{ $part->drawingUrl() }}" onerror="if(this.dataset.fallback&&this.src!==this.dataset.fallback){this.src=this.dataset.fallback;this.classList.add('p-4')}"
             alt="" loading="lazy" class="h-full w-full {{ $isPhoto ? 'object-contain p-2' : 'object-contain p-4' }}">
        <x-shop.ribbon :part="$part" />
    </a>
    <x-shop.wish-button :part="$part" class="absolute right-2.5 top-2.5 shadow-sm" />
    </div>

    {{-- name and specifications --}}
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($part->brand) <span class="rounded-full bg-[#16181B] px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-white">{{ $part->brand }}</span> @endif
            @if ($part->subcategory) <span class="text-[#6B7280]">{{ __($part->subcategory) }}</span> @endif
        </div>
        <h3 class="mt-2 text-lg font-semibold leading-snug sm:text-xl">
            <a href="{{ $url }}" :href="v[sel].url" wire:navigate class="hover:text-brand-600" x-text="v[sel].name">{{ $part->name }}</a>
        </h3>
        @if ($summary) <p class="mt-1 text-[15px] text-[#4B5563]">{{ $summary }}</p> @endif
        @if ($fits = $part->fitsText())
            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[13px] font-medium text-emerald-800">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 15v-3l2-5h11l3 5h1a1 1 0 0 1 1 1v2h-2"/><circle cx="7" cy="16" r="2"/><circle cx="16" cy="16" r="2"/><path d="M9 16h5"/></svg>
                {{ __('Fits :cars', ['cars' => $fits]) }}
            </p>
        @endif

        @if ($rows)
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-[#16181B]/10 pt-4 text-sm">
                @foreach ($rows as $k => $val)
                    <div class="min-w-0" @if ($loop->index >= $shown) x-show="all" x-cloak @endif>
                        <dt class="text-xs text-[#6B7280]">{{ __($k) }}</dt>
                        <dd class="mt-0.5 font-medium leading-snug text-[#16181B]">{{ \App\Models\Part::specDisplay($k, (string) $val) }}</dd>
                    </div>
                @endforeach
            </dl>
            @if (count($rows) > $shown)
                <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:underline" @click="all = !all" :aria-expanded="all">
                    <span x-text="all ? @js(__('Fewer details')) : @js(__('All :n details', ['n' => count($rows)]))">{{ __('All :n details', ['n' => count($rows)]) }}</span>
                    <svg class="h-4 w-4 transition-transform" :class="all && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
                </button>
            @endif
        @endif
    </div>

    {{-- price panel: the price follows the quantity --}}
    <div class="flex flex-col gap-4 rounded-xl bg-[#F7F8F9] p-4 sm:col-span-2 lg:col-span-1 lg:self-start">
        <span x-show="it.stock <= 0" @if ($stock > 0) x-cloak @endif class="inline-flex w-fit items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><circle cx="12" cy="12" r="9"/><path d="m8 8 8 8"/></svg> {{ __('Out of stock') }}
        </span>

        <div>
            <p class="text-sm text-[#6B7280] line-through" x-show="it.n > 0" x-text="tzs(it.n * qty)" @if (! $part->hasDiscount()) x-cloak @endif>{{ $part->hasDiscount() ? money($part->selling_price * $startQty) : '' }}</p>
            <p class="s-price text-[32px] leading-none" :class="it.n > 0 && 'text-brand-600'" x-text="tzs(it.p * qty)">{{ money($part->salePrice() * $startQty) }}</p>
            <p class="mt-1.5 text-sm text-[#6B7280]" x-text="qty > 1 ? qty + ' × ' + tzs(it.p) : {{ Js::from(__('per')) }} + ' ' + it.unit">{{ $startQty > 1 ? $startQty.' × '.money($part->salePrice()) : __('per').' '.$opts[$part->id]['unit'] }}</p>
        </div>

        @if ($sized)
            <div class="grid grid-cols-3 gap-1.5" role="radiogroup" aria-label="{{ __('Pack size') }}">
                @foreach ($family as $fv)
                    <button type="button" role="radio" @click="sel = {{ $fv->id }}; qty = 1" :aria-checked="sel === {{ $fv->id }}"
                            class="rounded-lg border bg-white px-1 py-2 text-center font-display text-lg font-bold leading-none transition-colors {{ $fv->shopStock() <= 0 ? 'text-[#9CA3AF]' : '' }}"
                            :class="sel === {{ $fv->id }} ? 'border-[#16181B] ring-1 ring-[#16181B]' : 'border-[#16181B]/15 hover:border-[#16181B]/40'">{{ $fv->packLabel() }}</button>
                @endforeach
            </div>
        @endif

        <div class="space-y-2" x-show="it.stock > 0" @if ($stock <= 0) x-cloak @endif>
            <div class="flex gap-2">
                <div class="flex flex-1 items-center justify-between rounded-md border border-[#16181B]/15 bg-white">
                    <button type="button" class="flex h-11 w-10 items-center justify-center rounded-md text-lg hover:bg-[#F1F2F4] disabled:text-[#D1D5DB]" @click="qty = Math.max(1, qty - 1)" :disabled="qty <= 1" aria-label="{{ __('One less') }}">−</button>
                    <input type="number" x-model.number="qty" @change="qty = Math.min(it.stock, Math.max(1, qty || 1))" min="1" :max="it.stock" class="h-11 w-10 border-0 bg-transparent p-0 text-center font-semibold focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" aria-label="{{ __('Quantity') }}">
                    <button type="button" class="flex h-11 w-10 items-center justify-center rounded-md text-lg hover:bg-[#F1F2F4] disabled:text-[#D1D5DB]" @click="qty = Math.min(it.stock, qty + 1)" :disabled="qty >= it.stock" aria-label="{{ __('One more') }}">+</button>
                </div>
                <button type="button" @click="$wire.addToCart(sel, qty); qty = {{ $startQty }}" title="{{ __('Add to cart') }}" aria-label="{{ __('Add to cart') }}"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md border border-[#16181B]/15 bg-white text-[#16181B] transition-colors hover:border-[#16181B] hover:bg-[#16181B] hover:text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/><path d="M13 9v4m-2-2h4"/></svg>
                </button>
            </div>
            <button type="button" class="s-btn-red h-11 w-full rounded-md py-0" @click="$wire.buyNow(sel, qty)" wire:loading.attr="disabled" wire:target="buyNow">{{ __('Buy now') }}</button>
        </div>
        <button type="button" x-show="it.stock <= 0" @if ($stock > 0) x-cloak @endif class="s-btn-line w-full rounded-md"
                @click="Livewire.dispatch('open-car-request', { parts: it.name })">{{ __('Ask us to get it') }}</button>
    </div>
</article>
