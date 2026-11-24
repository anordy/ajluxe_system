@php
    $wa = setting('whatsapp') ?: setting('phone');
    $waBase = $wa ? 'https://wa.me/'.\App\Support\Phone::international($wa) : null;
    $tones = [
        'red'  => ['bg' => 'bg-brand-500', 'text' => 'text-white', 'sub' => 'text-white/85', 'kick' => 'text-white/80', 'btn' => 'bg-white text-[#16181B] hover:bg-white/90', 'art' => 'bg-white/10'],
        'ink'  => ['bg' => 'bg-[#16181B]', 'text' => 'text-white', 'sub' => 'text-white/70', 'kick' => 'text-brand-500', 'btn' => 'bg-brand-500 text-white hover:bg-brand-600', 'art' => 'bg-white/5'],
        'grey' => ['bg' => 'bg-[#E9EBEE]', 'text' => 'text-[#16181B]', 'sub' => 'text-[#4B5563]', 'kick' => 'text-brand-600', 'btn' => 'bg-[#16181B] text-white hover:bg-black', 'art' => 'bg-white/60'],
    ];
@endphp
<div>
    {{-- ============================== HERO: find parts for your car + scrolling offers --}}
    <section class="bg-[#F1F2F4] pb-10 pt-6 lg:pt-8">
        <div class="wrap grid gap-5 lg:grid-cols-12">
            {{-- car finder --}}
            <div class="flex flex-col border-t-4 border-brand-500 bg-white p-6 shadow-[0_1px_2px_rgba(22,24,27,.06),0_0_0_1px_rgba(22,24,27,.06)] lg:col-span-4">
                <h1 class="font-display text-[32px] font-bold leading-[1.05]">{{ webtext('web_hero_title') }}</h1>
                @if (webtext('web_hero_text')) <p class="mt-2 text-[15px] text-[#4B5563]">{{ webtext('web_hero_text') }}</p> @endif
                <form wire:submit="findParts" class="mt-5 space-y-3">
                    <label class="flex items-center gap-3 rounded-md border px-3 focus-within:border-[#16181B] {{ $make ? 'border-[#16181B]/15' : 'border-brand-500' }}">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ $make ? 'bg-[#16181B]' : 'bg-brand-500' }}">1</span>
                        <span class="sr-only">{{ __('Car make') }}</span>
                        <select wire:model.live="make" class="h-12 w-full border-0 bg-transparent px-0 text-[15px] focus:ring-0">
                            <option value="">{{ __('Choose make') }}</option>
                            @foreach ($makes as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                        </select>
                    </label>
                    <label class="flex items-center gap-3 rounded-md border border-[#16181B]/15 px-3 focus-within:border-[#16181B] {{ $make ? '' : 'opacity-60' }}">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#E5E7EB] text-xs font-bold text-[#4B5563]">2</span>
                        <span class="sr-only">{{ __('Model') }}</span>
                        <select wire:model.live="model" class="h-12 w-full border-0 bg-transparent px-0 text-[15px] focus:ring-0" @disabled(! $make)>
                            <option value="">{{ $make ? __('Any :make model', ['make' => $make]) : __('Choose model') }}</option>
                            @foreach ($models as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                        </select>
                    </label>
                    <label class="flex items-center gap-3 rounded-md border border-[#16181B]/15 px-3 focus-within:border-[#16181B] {{ $engines ? '' : 'opacity-60' }}">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#E5E7EB] text-xs font-bold text-[#4B5563]">3</span>
                        <span class="sr-only">{{ __('Engine') }}</span>
                        <select wire:model="engine" class="h-12 w-full border-0 bg-transparent px-0 text-[15px] focus:ring-0" @disabled(! $engines)>
                            <option value="">{{ ! $model ? __('Choose engine') : ($engines ? __('Any engine') : __('Any engine (not listed for this model)')) }}</option>
                            @foreach ($engines as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                        </select>
                    </label>
                    <button class="s-btn-red w-full py-3.5" @disabled(! $make)>{{ __('Show parts that fit') }}</button>
                </form>
                <button type="button" @click="Livewire.dispatch('open-car-request', { make: @js($make), model: @js($model) })"
                        class="group mt-4 flex w-full items-center gap-3 rounded-md bg-[#F4F5F7] px-4 py-3 text-left hover:bg-[#ECEEF1]">
                    <svg class="h-8 w-8 shrink-0 text-[#16181B]" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 30v-6l5-9h22l7 9h2a2 2 0 0 1 2 2v4h-4"/><circle cx="14" cy="31" r="4"/><circle cx="34" cy="31" r="4"/><path d="M18 31h12M6 30h4"/><circle cx="36" cy="12" r="7" fill="#fff"/><path d="M36 9v4M36 15.5v.5"/></svg>
                    <span class="min-w-0 flex-1 leading-tight"><span class="block font-semibold">{{ __('Can\'t find your car?') }}</span>
                        <span class="text-sm text-brand-600 group-hover:underline">{{ __('Send us your car details') }}</span></span>
                    <svg class="h-4 w-4 shrink-0 text-[#4B5563]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                </button>
            </div>

            {{-- scrolling offers --}}
            <div class="flex flex-col lg:col-span-8"
                 x-data="{ i: 0, n: {{ count($slides) }}, paused: false, timer: null,
                    go(k) { this.i = (k + this.n) % this.n },
                    start() { if (matchMedia('(prefers-reduced-motion: reduce)').matches) return; this.timer = setInterval(() => { if (! this.paused && ! document.hidden) this.go(this.i + 1) }, 6000) } }"
                 x-init="start()" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false"
                 role="region" aria-roledescription="carousel" aria-label="{{ __('Offers') }}">
                <div class="relative h-[360px] overflow-hidden rounded-md sm:h-[400px] lg:h-auto lg:min-h-[400px] lg:flex-1">
                    @foreach ($slides as $k => $s)
                        @php $t = $tones[$s['tone']] ?? $tones['ink']; @endphp
                        @if ($s['imageOnly'] ?? false)
                            {{-- picture-only banner: the uploaded design fills the whole slide --}}
                            <div x-show="i === {{ $k }}" x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0" x-transition:leave="transition duration-300 absolute inset-0" x-transition:leave-end="opacity-0"
                                 @if ($k > 0) x-cloak @endif class="absolute inset-0 bg-[#E9EBEE]" aria-roledescription="slide" aria-label="{{ $k + 1 }} of {{ count($slides) }}">
                                {{-- phones: the whole design over a blurred copy of itself; larger screens: fills the slide --}}
                                <img src="{{ $s['img'] }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-110 object-cover blur-xl sm:hidden">
                                @if ($s['href'])
                                    <a href="{{ $s['href'] }}" wire:navigate class="relative block h-full w-full"><img src="{{ $s['img'] }}" alt="{{ $s['title'] }}" class="h-full w-full object-contain sm:object-cover"></a>
                                @else
                                    <img src="{{ $s['img'] }}" alt="{{ $s['title'] }}" class="relative h-full w-full object-contain sm:object-cover">
                                @endif
                            </div>
                            @continue
                        @endif
                        <div x-show="i === {{ $k }}" x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0" x-transition:leave="transition duration-300 absolute inset-0" x-transition:leave-end="opacity-0"
                             @if ($k > 0) x-cloak @endif class="absolute inset-0 grid grid-cols-1 sm:grid-cols-[1.15fr_1fr] {{ $t['bg'] }}" aria-roledescription="slide" aria-label="{{ $k + 1 }} of {{ count($slides) }}">
                            <div class="flex flex-col justify-center p-7 sm:p-10">
                                @if ($s['kicker']) <p class="text-sm font-semibold {{ $t['kick'] }}">{{ $s['kicker'] }}</p> @endif
                                <h2 class="mt-2 font-display text-[34px] font-extrabold leading-[1] sm:text-[44px] {{ $t['text'] }}">{{ $s['title'] }}</h2>
                                @if ($s['text']) <p class="mt-3 max-w-md text-[15px] leading-relaxed {{ $t['sub'] }}">{{ $s['text'] }}</p> @endif
                                <a href="{{ $s['url'] }}" wire:navigate class="s-btn mt-6 w-fit {{ $t['btn'] }}">{{ $s['cta'] }}</a>
                            </div>
                            <div class="relative hidden items-center justify-center p-6 sm:flex {{ $t['art'] }}">
                                @if ($s['img'])
                                    <img src="{{ $s['img'] }}" alt="" class="{{ ($s['photo'] ?? false) ? 'absolute inset-0 h-full w-full object-cover' : 'max-h-[280px] w-full object-contain' }}">
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if (count($slides) > 1)
                    <div class="mt-3 flex items-center justify-center gap-3">
                        <button type="button" @click="go(i - 1)" class="flex h-9 w-9 items-center justify-center border border-[#16181B]/20 bg-white hover:border-[#16181B]" aria-label="{{ __('Previous offer') }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m15 6-6 6 6 6"/></svg></button>
                        @foreach ($slides as $k => $s)
                            <button type="button" @click="go({{ $k }})" class="h-2.5 rounded-full transition-all" :class="i === {{ $k }} ? 'w-7 bg-[#16181B]' : 'w-2.5 bg-[#16181B]/25 hover:bg-[#16181B]/50'" aria-label="{{ __('Show offer :n', ['n' => $k + 1]) }}"></button>
                        @endforeach
                        <button type="button" @click="go(i + 1)" class="flex h-9 w-9 items-center justify-center border border-[#16181B]/20 bg-white hover:border-[#16181B]" aria-label="{{ __('Next offer') }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 6 6 6-6 6"/></svg></button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ============================== WHY BUY HERE (Website texts › trust points) --}}
    @php $trust = \App\Support\Settings::pairs('web_trust'); $icons = ['shield', 'wrench', 'wallet', 'clock']; @endphp
    @if ($trust)
        <section class="border-b border-[#16181B]/10 bg-white">
            <ul class="wrap grid gap-x-8 gap-y-5 py-7 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($trust as $i => [$h, $t])
                    <li class="flex items-start gap-3.5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#FDECEC] text-brand-600">
                            @switch($icons[$i % 4])
                                @case('shield') <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6l-8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg> @break
                                @case('wrench') <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a4 4 0 0 0 5 5L21 13l-8 8-3-3 6.3-6.3"/><path d="M14.7 6.3 9 12l-6 6 3 3 6-6"/><path d="M14.7 6.3 17 4l3 3-2.3 2.3"/></svg> @break
                                @case('wallet') <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 15h2"/></svg> @break
                                @default <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            @endswitch
                        </span>
                        <span><span class="block font-semibold leading-snug">{{ $h }}</span>@if ($t)<span class="text-sm text-[#4B5563]">{{ $t }}</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ============================== SHOP BY CATEGORY --}}
    <section class="wrap pt-12">
        <div class="mb-5 flex items-end justify-between gap-4">
            <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ __('Shop by category') }}</h2>
            <p class="hidden text-[#4B5563] sm:block">{{ __(':n parts in stock', ['n' => number_format($inStockCount)]) }}</p>
        </div>
        <x-shop.category-search />
    </section>

    {{-- ============================== HOT RIGHT NOW --}}
    @php $hotRow = $hot->isNotEmpty() ? $hot : $fresh; @endphp
    @if ($hotRow->isNotEmpty())
        <section class="wrap pt-14">
            <div class="mb-5 flex items-end justify-between gap-4">
                <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ $hot->isNotEmpty() ? __('Hot right now') : __('In stock now') }}</h2>
                <a href="{{ route('shop.catalog', $hot->isNotEmpty() ? ['deals' => 1] : []) }}" wire:navigate class="font-semibold text-brand-600 hover:underline">{{ $hot->isNotEmpty() ? __('All deals') : __('All parts') }}</a>
            </div>
            <x-shop.scroller label="Deals">
                @foreach ($hotRow as $p) <div class="w-[230px] shrink-0 snap-start sm:w-[250px]" wire:key="h{{ $p->id }}"><x-shop.part-card :part="$p" /></div> @endforeach
            </x-shop.scroller>
        </section>
    @endif

    {{-- ============================== SHOP BY BRAND --}}
    @if ($brands->count() > 2)
        <section class="wrap pt-14">
            <div class="mb-5 flex items-end justify-between gap-4">
                <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ __('Shop by brand') }}</h2>
                <a href="{{ route('shop.catalog') }}" wire:navigate class="font-semibold text-brand-600 hover:underline">{{ __('All parts') }}</a>
            </div>
            <div class="grid grid-cols-2 gap-px overflow-hidden rounded-md border border-[#16181B]/10 bg-[#16181B]/10 sm:grid-cols-4 lg:grid-cols-8">
                @foreach ($brands as $b => $n)
                    <a href="{{ route('shop.catalog', ['brands' => [$b]]) }}" wire:navigate class="flex h-24 flex-col items-center justify-center bg-white px-2 text-center hover:bg-[#F4F5F7]">
                        <span class="w-full truncate font-display text-[22px] font-extrabold leading-none tracking-tight">{{ $b }}</span>
                        <span class="mt-1.5 text-xs text-[#6B7280]">{{ trans_choice(':count product|:count products', $n) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============================== GARAGE & PAINT (Website texts › garage) --}}
    <section class="mt-16 bg-[#16181B] text-white">
        <div class="wrap grid items-center gap-10 py-14 lg:grid-cols-2">
            <div>
                <h2 class="max-w-xl font-display text-4xl font-extrabold leading-[1.02] tracking-tight sm:text-[46px]">{{ webtext('web_garage_title') }}</h2>
                <p class="mt-5 max-w-lg text-white/70">{{ webtext('web_garage_text') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('shop.garage') }}#book" wire:navigate class="s-btn-red">{{ __('Book your car in') }}</a>
                    <a href="{{ route('shop.garage') }}" wire:navigate class="s-btn border border-white/30 hover:border-white">{{ __('What we do') }}</a>
                </div>
            </div>
            @php $offer = array_slice(\App\Support\Settings::pairs('web_garage_services'), 0, 6); @endphp
            @if ($offer)
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ($offer as [$h, $t])
                        <li class="flex gap-4 rounded-xl bg-white/[0.06] p-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-500/15 text-brand-500"><x-shop.service-icon :title="$h" class="h-5 w-5" /></span>
                            <span><span class="block font-semibold">{{ $h }}</span>@if ($t)<span class="text-sm text-white/60">{{ $t }}</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- ============================== IMPORT FOR YOU --}}
    <section class="wrap pt-14">
        <div class="grid overflow-hidden rounded-md border border-[#16181B]/10 bg-white lg:grid-cols-12">
            <div class="p-8 lg:col-span-7 lg:p-10">
                <p class="text-sm font-semibold text-brand-600">{{ __('Not in stock?') }}</p>
                <h2 class="mt-1 font-display text-4xl font-extrabold leading-none tracking-tight">{{ webtext('web_import_title') }}</h2>
                <p class="mt-4 max-w-xl text-[#4B5563]">{{ webtext('web_import_text') }}</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach (['Engine', 'Gearbox / transmission', 'Body parts & lights', 'Electrical & ECU'] as $t)
                        <a href="{{ route('shop.import', ['item' => $t]) }}#request" wire:navigate class="rounded-md border border-[#16181B]/15 px-4 py-2 text-sm font-semibold hover:border-[#16181B]">{{ __($t) }}</a>
                    @endforeach
                </div>
                <a href="{{ route('shop.import') }}" wire:navigate class="s-btn-red mt-7">{{ __('Ask for a price') }}</a>
            </div>
            <ul class="grid content-center gap-px bg-[#16181B]/10 lg:col-span-5">
                @foreach (array_slice(\App\Support\Settings::pairs('web_import_countries'), 0, 4) as [$c, $t])
                    <li class="bg-[#F4F5F7] px-6 py-4"><span class="block font-semibold">{{ $c }}</span><span class="text-sm text-[#4B5563]">{{ $t }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ============================== TYRES / OIL rows --}}
    @foreach ([['Tyres', $tyres, __('Tyres in stock')], ['Lubricants', $oils, __('Lubricants')]] as [$dep, $rows, $title])
        @if ($rows->isNotEmpty())
            <section class="wrap pt-14">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ $title }}</h2>
                    <a href="{{ route('shop.catalog', ['category' => $dep]) }}" wire:navigate class="font-semibold text-brand-600 hover:underline">{{ __('See all :department', ['department' => mb_strtolower(__($dep))]) }}</a>
                </div>
                <x-shop.scroller :label="$title">
                    @foreach ($rows as $p) <div class="w-[230px] shrink-0 snap-start sm:w-[250px]" wire:key="{{ $dep }}{{ $p->id }}"><x-shop.part-card :part="$p" /></div> @endforeach
                </x-shop.scroller>
            </section>
        @endif
    @endforeach

    {{-- ============================== HOW ORDERING WORKS (a real sequence) --}}
    <section class="wrap pt-16">
        <h2 class="font-display text-4xl font-extrabold tracking-tight">{{ __('How ordering works') }}</h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (\App\Support\Settings::pairs('web_steps') as $i => [$h, $t])
                <li class="border-t-4 {{ $i === 0 ? 'border-brand-500' : 'border-[#16181B]' }} pt-4">
                    <span class="font-display text-5xl font-extrabold leading-none text-[#16181B]/15">{{ $i + 1 }}</span>
                    <h3 class="mt-2 text-lg font-semibold">{{ $h }}</h3>
                    <p class="mt-1 text-[#4B5563]">{{ $t }}</p>
                </li>
            @endforeach
        </ol>
    </section>

</div>
