@php
    $me = auth('shop')->user();
    $wa = setting('whatsapp') ?: setting('phone');
    $waLink = $wa ? 'https://wa.me/'.\App\Support\Phone::international($wa) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ setting('business_name') }} — {{ __('Spare parts & garage') }}</title>
    <meta name="description" content="{{ webtext('business_tagline') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ asset('icon-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/shop.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('Skip to content') }}</a>

    {{-- top bar (Website texts › top bar) --}}
    <div class="hidden bg-black text-[13px] text-white/75 sm:block">
        <div class="wrap flex h-9 items-center gap-6">
            @if (webtext('web_topbar')) <p class="truncate">{{ webtext('web_topbar') }}</p> @endif
            <div class="ml-auto flex shrink-0 items-center gap-5">
                @if (setting('opening_hours')) <span>{{ opening_hours() }}</span> @endif
                @if (setting('phone')) <a href="tel:{{ preg_replace('/\s+/', '', setting('phone')) }}" class="font-semibold text-white hover:underline">{{ setting('phone') }}</a> @endif
                <button type="button" @click="Livewire.dispatch('open-car-request')" class="hover:text-white">{{ __('Can\'t find your car?') }}</button>
                @php $langs = \App\Http\Middleware\SetShopLocale::LOCALES; $here = app()->getLocale(); @endphp
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="{{ __('Language') }}: {{ $langs[$here] ?? 'English' }}"
                            class="flex items-center gap-2 rounded px-2 py-0.5 font-semibold text-white hover:bg-white/10">
                        <x-shop.flag :code="$here" /> {{ $langs[$here] ?? 'English' }}
                        <svg class="h-3 w-3 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <ul x-show="open" x-cloak x-transition.opacity.duration.100ms class="absolute right-0 top-full z-50 mt-1 w-40 overflow-hidden rounded-md bg-white py-1 text-[14px] text-[#16181B] shadow-lg ring-1 ring-black/10">
                        @foreach ($langs as $code => $name)
                            <li>
                                <a href="{{ route('shop.language', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}" @if ($code === $here) aria-current="true" @endif
                                   class="flex items-center gap-2.5 px-3 py-2 hover:bg-[#F4F5F7] {{ $code === $here ? 'font-semibold' : '' }}">
                                    <x-shop.flag :code="$code" /> <span class="flex-1">{{ $name }}</span>
                                    @if ($code === $here) <svg class="h-4 w-4 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg> @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- header: brand, search, account, cart --}}
    <header x-data="{ menu: false }" class="sticky top-0 z-30 bg-[#16181B] text-white shadow-[0_1px_0_rgba(255,255,255,.08)]">
        <div class="wrap flex h-[68px] items-center gap-3 lg:gap-6">
            <a href="{{ route('shop.home') }}" wire:navigate class="flex shrink-0 items-center gap-3" aria-label="{{ __(':name home', ['name' => setting('business_name')]) }}">
                <span class="rounded-md bg-white px-2.5 py-1.5"><img src="{{ logo_url() }}" alt="" class="h-9 w-auto"></span>
            </a>

            <form action="{{ route('shop.catalog') }}" method="GET" class="hidden flex-1 md:flex" role="search">
                <label for="q-top" class="sr-only">{{ __('Search parts') }}</label>
                <input id="q-top" name="q" value="{{ request('q') }}" class="h-11 w-full rounded-l-md border-0 bg-white px-4 text-[15px] text-[#16181B] placeholder:text-[#6B7280] focus:ring-2 focus:ring-brand-500" placeholder="{{ __('Part name, part number or car model') }}">
                <button class="flex h-11 shrink-0 items-center gap-2 rounded-r-md bg-brand-500 px-5 font-semibold hover:bg-brand-600">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <span class="hidden lg:inline">{{ __('Search') }}</span>
                </button>
            </form>

            <div class="ml-auto flex items-center gap-1 md:ml-0">
                @if ($me)
                    <a href="{{ route('shop.account') }}" wire:navigate class="hidden items-center gap-2 rounded-md px-3 py-2 hover:bg-white/10 sm:flex">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                        <span class="text-sm leading-tight"><span class="block text-white/60">{{ __('Hello, :name', ['name' => $me->firstName()]) }}</span><span class="font-semibold">{{ __('My orders') }}</span></span>
                    </a>
                @else
                    <a href="{{ route('shop.login') }}" wire:navigate class="hidden rounded-md px-3 py-2 text-[15px] font-semibold hover:bg-white/10 sm:block">{{ __('Log in') }}</a>
                    <a href="{{ route('shop.register') }}" wire:navigate class="hidden rounded-md bg-white px-4 py-2 text-[15px] font-semibold text-[#16181B] hover:bg-white/90 sm:block">{{ __('Sign up') }}</a>
                @endif
                <livewire:shop.wishlist-button />
                <livewire:shop.cart-button />
                <button type="button" class="rounded-md p-2 hover:bg-white/10 md:hidden" @click="menu = !menu" :aria-expanded="menu" aria-label="{{ __('Menu') }}">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>

        {{-- category bar: the first departments, the rest under "More" --}}
        @php
            $navCat = request('category');
            $deps = \App\Models\Part::shopDepartments();
            $inline = array_slice($deps, 0, 5);
            $more = array_slice($deps, 5);
            $moreOn = in_array($navCat, $more, true);
            $on = 'text-white after:absolute after:inset-x-3 after:bottom-0 after:h-[3px] after:bg-brand-500';
        @endphp
        <nav class="border-t border-white/10 bg-black/40" aria-label="{{ __('Departments') }}">
            <div class="wrap flex h-12 items-stretch gap-1 text-[15px] font-semibold">
                <div class="flex min-w-0 flex-1 items-stretch gap-1 overflow-x-auto [scrollbar-width:none] lg:overflow-visible">
                    <a href="{{ route('shop.catalog') }}" wire:navigate class="flex shrink-0 items-center gap-2 border-r border-white/10 pr-4 {{ request()->routeIs('shop.catalog') && ! $navCat && ! request('deals') ? 'text-white' : 'text-white/80 hover:text-white' }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg> {{ __('All parts') }}
                    </a>
                    <a href="{{ route('shop.catalog', ['deals' => 1]) }}" wire:navigate class="flex shrink-0 items-center gap-2 px-3 {{ request('deals') ? 'text-white' : 'text-white/80 hover:text-white' }}">{{ __('Deals') }} <span class="rounded bg-brand-500 px-1.5 py-0.5 text-[11px] font-bold leading-none text-white">{{ __('Sale') }}</span></a>
                    @foreach ($inline as $dep)
                        <a href="{{ route('shop.catalog', ['category' => $dep]) }}" wire:navigate class="relative flex shrink-0 items-center px-3 {{ $navCat === $dep ? $on : 'text-white/80 hover:text-white' }}">{{ __($dep) }}</a>
                    @endforeach
                    {{-- phones scroll the row, so the extra departments simply follow --}}
                    @foreach ($more as $dep)
                        <a href="{{ route('shop.catalog', ['category' => $dep]) }}" wire:navigate class="relative flex shrink-0 items-center px-3 lg:hidden {{ $navCat === $dep ? $on : 'text-white/80 hover:text-white' }}">{{ __($dep) }}</a>
                    @endforeach
                    <a href="{{ route('shop.import') }}" wire:navigate class="flex shrink-0 items-center px-3 text-white/80 hover:text-white md:hidden">{{ __('Import for you') }}</a>
                    <a href="{{ route('shop.garage') }}" wire:navigate class="flex shrink-0 items-center px-3 text-white/80 hover:text-white md:hidden">{{ __('Garage & paint') }}</a>
                    @if ($more)
                        <div class="relative hidden shrink-0 lg:flex" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                            <button type="button" @click="open = !open" :aria-expanded="open" class="relative flex items-center gap-1.5 px-3 {{ $moreOn ? $on : 'text-white/80 hover:text-white' }}">
                                {{ __('More') }} <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity class="absolute left-0 top-full z-40 mt-px w-60 rounded-b-md bg-white py-2 text-[#16181B] shadow-xl">
                                @foreach ($more as $dep)
                                    <a href="{{ route('shop.catalog', ['category' => $dep]) }}" wire:navigate class="block px-4 py-2 font-medium hover:bg-[#F4F5F7] {{ $navCat === $dep ? 'text-brand-600' : '' }}">{{ __($dep) }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <a href="{{ route('shop.import') }}" wire:navigate class="relative hidden shrink-0 items-center gap-1.5 border-l border-white/10 px-4 md:flex {{ request()->routeIs('shop.import') ? 'text-white' : 'text-white/80 hover:text-white' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 20h20M5 17l-2-6 3 1 3 3h11a2 2 0 0 1 0 4H8"/><path d="m9 12 1-7 3 1 1 7"/></svg> {{ __('Import for you') }}
                </a>
                <a href="{{ route('shop.garage') }}" wire:navigate class="relative hidden shrink-0 items-center border-l border-white/10 pl-4 pr-1 md:flex {{ request()->routeIs('shop.garage') ? 'text-white' : 'text-white/80 hover:text-white' }}">{{ __('Garage & paint') }}</a>
            </div>
        </nav>

        {{-- mobile menu --}}
        <div x-show="menu" x-cloak x-transition.opacity class="border-t border-white/10 bg-[#16181B] md:hidden" @click.outside="menu = false">
            <div class="wrap space-y-3 py-4">
                <form action="{{ route('shop.catalog') }}" method="GET" role="search" class="flex">
                    <input name="q" class="h-11 w-full rounded-l-md border-0 px-3 text-[#16181B]" placeholder="{{ __('Search parts') }}" aria-label="{{ __('Search parts') }}">
                    <button class="rounded-r-md bg-brand-500 px-4 font-semibold">{{ __('Search') }}</button>
                </form>
                <div class="flex gap-2">
                    @if ($me)
                        <a href="{{ route('shop.account') }}" class="s-btn flex-1 bg-white text-[#16181B]">{{ __('My orders') }}</a>
                    @else
                        <a href="{{ route('shop.login') }}" class="s-btn flex-1 border border-white/30">{{ __('Log in') }}</a>
                        <a href="{{ route('shop.register') }}" class="s-btn-red flex-1">{{ __('Sign up') }}</a>
                    @endif
                </div>
                <div class="flex gap-2 text-sm" aria-label="{{ __('Language') }}">
                    @foreach (\App\Http\Middleware\SetShopLocale::LOCALES as $code => $name)
                        <a href="{{ route('shop.language', $code) }}" lang="{{ $code }}" class="flex flex-1 items-center justify-center gap-2 rounded-md border px-3 py-2 font-semibold {{ app()->getLocale() === $code ? 'border-white bg-white text-[#16181B]' : 'border-white/30 text-white' }}"><x-shop.flag :code="$code" /> {{ $name }}</a>
                    @endforeach
                </div>
                <p class="text-sm text-white/60">{{ opening_hours() }}@if (setting('phone')) · <a href="tel:{{ preg_replace('/\s+/', '', setting('phone')) }}" class="text-white">{{ setting('phone') }}</a>@endif</p>
            </div>
        </div>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    {{-- footer --}}
    <footer class="mt-20 bg-[#16181B] text-white/70">
        <div class="wrap grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <span class="inline-flex rounded-md bg-white p-2"><img src="{{ logo_url() }}" alt="{{ setting('business_name') }}" class="h-10"></span>
                <p class="mt-4 max-w-sm text-sm leading-relaxed">{{ webtext('business_tagline') }}</p>
                <ul class="mt-5 space-y-1.5 text-sm">
                    @if (setting('address')) <li>{{ setting('address') }}</li> @endif
                    @if (setting('phone')) <li><a href="tel:{{ preg_replace('/\s+/', '', setting('phone')) }}" class="hover:text-white">{{ setting('phone') }}</a></li> @endif
                    @if (setting('email')) <li><a href="mailto:{{ setting('email') }}" class="hover:text-white">{{ setting('email') }}</a></li> @endif
                    @if (setting('opening_hours')) <li>{{ opening_hours() }}</li> @endif
                </ul>
            </div>
            <div class="lg:col-span-3">
                <h2 class="font-display text-lg font-bold text-white">{{ __('Shop') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach (\App\Models\Part::shopDepartments() as $dep)
                        <li><a href="{{ route('shop.catalog', ['category' => $dep]) }}" wire:navigate class="hover:text-white">{{ __($dep) }}</a></li>
                    @endforeach
                    <li><a href="{{ route('shop.catalog', ['deals' => 1]) }}" wire:navigate class="hover:text-white">{{ __('Deals') }}</a></li>
                </ul>
            </div>
            <div class="lg:col-span-2">
                <h2 class="font-display text-lg font-bold text-white">{{ __('Help') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ $me ? route('shop.account') : route('shop.login') }}" wire:navigate class="hover:text-white">{{ __('My account & orders') }}</a></li>
                    <li><button type="button" @click="Livewire.dispatch('open-car-request')" class="text-left hover:text-white">{{ __('Can\'t find your car?') }}</button></li>
                    <li><a href="{{ route('shop.import') }}" wire:navigate class="hover:text-white">{{ __('Import for you') }}</a></li>
                    <li><a href="{{ route('shop.garage') }}" wire:navigate class="hover:text-white">{{ __('Garage & paint') }}</a></li>
                    <li><a href="{{ route('shop.cart') }}" wire:navigate class="hover:text-white">{{ __('Cart') }}</a></li>
                </ul>
            </div>
            <div class="lg:col-span-3">
                <h2 class="font-display text-lg font-bold text-white">{{ __('Ways to pay') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                    @foreach (preg_split('/\r\n|\r|\n|,/', (string) webtext('web_pay_methods')) as $row)
                        @if (trim($row) !== '') <li class="rounded-md border border-white/15 px-3 py-1.5 font-semibold text-white/90">{{ trim($row) }}</li> @endif
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="wrap flex flex-wrap items-center justify-between gap-3 py-5 text-xs">
                <span>&copy; {{ date('Y') }} {{ setting('business_name') }}</span>
                <a href="{{ route('login') }}" class="hover:text-white">{{ __('Staff login') }}</a>
            </div>
        </div>
    </footer>

    <livewire:shop.car-request-form />

    {{-- small notices (added to cart, etc.) --}}
    <div x-data="{ items: [], add(t) { const id = Date.now() + Math.random(); this.items.push({ id, ...t }); setTimeout(() => this.items = this.items.filter(i => i.id !== id), 3500); } }"
         x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
         @toast.window="add($event.detail)"
         class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
        <template x-for="t in items" :key="t.id">
            <div class="pointer-events-auto rounded-md px-4 py-3 text-sm font-semibold shadow-lg"
                 :class="t.type === 'error' ? 'bg-brand-600 text-white' : 'bg-[#16181B] text-white'" x-text="t.message"></div>
        </template>
    </div>

    @if ($waLink)
        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg hover:brightness-95" aria-label="{{ __('Chat with us on WhatsApp') }}">
            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.4-.2Z"/></svg>
        </a>
    @endif

    @livewireScripts
</body>
</html>
