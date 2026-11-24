<div>
    @if ($lines->isNotEmpty()) <x-shop.checkout-steps :step="1" /> @endif
<div class="wrap pt-10">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="font-display text-5xl font-extrabold tracking-tight">{{ __('Your cart') }}</h1>
        @if ($lines->isNotEmpty())
            <p class="text-[#4B5563]">{{ trans_choice(':count item|:count items', $lines->sum('qty')) }}</p>
        @endif
    </div>

    @if ($lines->isEmpty())
        <div class="mt-8 flex flex-col items-center rounded-2xl bg-[#F4F5F7] px-6 py-16 text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white text-[#16181B] shadow-sm">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>
            </span>
            <p class="mt-5 text-xl font-semibold">{{ __('Your cart is empty') }}</p>
            <p class="mt-1 text-[#4B5563]">{{ __('Find the parts you need and add them here.') }}</p>
            <a href="{{ route('shop.catalog') }}" wire:navigate class="s-btn-red mt-6 rounded-md">{{ __('Shop parts') }}</a>
        </div>
    @else
        <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_380px]">
            <ul class="space-y-3">
                @foreach ($lines as $l)
                    @php $p = $l->part; @endphp
                    <li class="flex gap-4 rounded-xl border border-[#16181B]/10 bg-white p-4 sm:gap-5" wire:key="l{{ $p?->id }}">
                        <a href="{{ $p ? route('shop.product', [$p, $p->slug()]) : '#' }}" wire:navigate class="relative h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-gradient-to-b from-[#F7F8F9] to-[#ECEEF1] sm:h-28 sm:w-28">
                            @if ($p)
                                <img src="{{ $p->thumbUrl() }}" loading="lazy" data-fallback="{{ $p->drawingUrl() }}" onerror="if(this.dataset.fallback&&this.src!==this.dataset.fallback){this.src=this.dataset.fallback;this.classList.add('p-3')}"
                                     alt="" class="h-full w-full {{ $p->imageUrl() && ! str_ends_with(strtolower((string) $p->image_path), '.svg') ? 'object-cover' : 'object-contain p-3' }}">
                            @endif
                            @if ($l->price < $l->list) <span class="absolute left-1.5 top-1.5 rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-bold text-white">−{{ round((1 - $l->price / $l->list) * 100) }}%</span> @endif
                        </a>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    @if ($p?->brand) <p class="text-xs font-bold uppercase tracking-wide text-[#6B7280]">{{ $p->brand }}</p> @endif
                                    <a href="{{ $p ? route('shop.product', [$p, $p->slug()]) : '#' }}" wire:navigate class="font-semibold leading-snug hover:text-brand-600">{{ $p?->name ?? __('Part no longer available') }}</a>
                                    <p class="mt-0.5 text-sm text-[#6B7280]">{{ __(':price each', ['price' => money($l->price)]) }} @if ($l->price < $l->list) <span class="line-through">{{ money($l->list) }}</span> @endif</p>
                                </div>
                                <button type="button" wire:click="remove({{ $p?->id ?? 0 }})" class="-mr-1 -mt-1 shrink-0 rounded-md p-2 text-[#9CA3AF] hover:bg-[#F4F5F7] hover:text-brand-600" title="{{ __('Remove') }}" aria-label="Remove {{ $p?->name }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                                </button>
                            </div>
                            @if ($l->problem) <p class="mt-1 text-sm font-semibold text-brand-600">{{ $l->problem }}</p> @endif
                            <div class="mt-auto flex flex-wrap items-end justify-between gap-3 pt-3">
                                @if ($l->available > 0)
                                    @php $q = min($l->qty, $l->available); @endphp
                                    <div class="flex items-center rounded-md border border-[#16181B]/15" role="group" aria-label="{{ __('Quantity') }}">
                                        <button type="button" wire:click="setQty({{ $p->id }}, {{ $q - 1 }})" @disabled($q <= 1) class="flex h-10 w-10 items-center justify-center rounded-md text-lg hover:bg-[#F4F5F7] disabled:text-[#D1D5DB] disabled:hover:bg-transparent" aria-label="{{ __('One less') }}">−</button>
                                        <span class="w-8 text-center font-semibold" aria-live="polite">{{ $q }}</span>
                                        <button type="button" wire:click="setQty({{ $p->id }}, {{ $q + 1 }})" @disabled($q >= $l->available) class="flex h-10 w-10 items-center justify-center rounded-md text-lg hover:bg-[#F4F5F7] disabled:text-[#D1D5DB] disabled:hover:bg-transparent" aria-label="{{ __('One more') }}">+</button>
                                    </div>
                                @else
                                    <span></span>
                                @endif
                                <p class="s-price text-2xl" wire:loading.class="opacity-50" wire:target="setQty">{{ money($l->amount) }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <aside class="rounded-xl border border-[#16181B]/10 bg-white p-6 shadow-[0_10px_30px_rgba(22,24,27,.06)] lg:sticky lg:top-40">
                <h2 class="font-display text-2xl font-extrabold">{{ __('Order summary') }}</h2>
                <dl class="mt-5 space-y-2.5 text-[15px]">
                    <div class="flex justify-between"><dt class="text-[#4B5563]">{{ __('Items (:n)', ['n' => $lines->sum('qty')]) }}</dt><dd>{{ money($totals['list']) }}</dd></div>
                    @if ($totals['discount'] > 0)
                        <div class="flex justify-between"><dt class="text-[#4B5563]">{{ __('Discount') }}</dt><dd class="font-semibold text-emerald-700">− {{ money($totals['discount']) }}</dd></div>
                    @endif
                    <div class="flex items-baseline justify-between border-t border-[#16181B]/10 pt-4"><dt class="text-lg font-semibold">{{ __('Total') }}</dt><dd class="s-price text-3xl">{{ money($totals['total']) }}</dd></div>
                </dl>
                @if ($totals['discount'] > 0)
                    <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">{{ __('You save :amount on this order.', ['amount' => money($totals['discount'])]) }}</p>
                @endif
                @if ($problem)
                    <p class="mt-4 text-sm font-semibold text-brand-600">{{ __('Fix the items marked in red to continue.') }}</p>
                    <button class="s-btn-red mt-3 w-full rounded-md py-3.5" disabled>{{ __('Go to checkout') }}</button>
                @else
                    <a href="{{ route('shop.checkout') }}" wire:navigate class="s-btn-red mt-5 w-full rounded-md py-3.5">{{ __('Go to checkout') }}</a>
                @endif
                <a href="{{ route('shop.catalog') }}" wire:navigate class="mt-3 block text-center text-sm font-semibold text-[#4B5563] hover:text-[#16181B]">{{ __('Keep shopping') }}</a>
                <ul class="mt-6 space-y-2 border-t border-[#16181B]/10 pt-5 text-sm text-[#4B5563]">
                    <li class="flex gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg> {{ __('We confirm every order by phone before you pay') }}</li>
                    <li class="flex gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg> {{ __('Pay by M-Pesa, bank or cash') }}</li>
                    <li class="flex gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg> {{ __('Collect at our shop, or delivery agreed with you') }}</li>
                </ul>
            </aside>
        </div>
    @endif
</div>
</div>
