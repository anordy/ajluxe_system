<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ setting('business_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ asset('icon-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $user = auth()->user();
    // [label, route name or null (= coming in a later phase), icon, roles allowed]
    $nav = [
        'Main' => [
            ['Dashboard', 'dashboard', 'home', ['admin', 'manager', 'mechanic', 'staff']],
        ],
        'Garage' => [
            ['Job Cards', 'job-cards.*', 'clipboard', ['admin', 'manager', 'mechanic']],
            ['Customers', 'customers.*', 'users', ['admin', 'manager', 'staff']],
            ['Vehicles', 'vehicles.index', 'car', ['admin', 'manager', 'staff', 'mechanic']],
            ['Services', 'services.index', 'wrench', ['admin', 'manager']],
            ['Mechanics', 'mechanics.index', 'users', ['admin', 'manager']],
        ],
        'Spare Parts' => [
            ['POS / New Sale', 'pos', 'cart', ['admin', 'manager', 'staff']],
            ['Online Orders', 'orders.index', 'doc', ['admin', 'manager', 'staff']],
            ['Parts Catalogue', 'parts.index', 'box', ['admin', 'manager', 'staff', 'mechanic']],
            ['Stock Register', 'stock.index', 'list', ['admin', 'manager', 'staff']],
            ['Purchase Orders', 'purchase-orders.*', 'truck', ['admin', 'manager']],
            ['Suppliers', 'suppliers.index', 'truck', ['admin', 'manager']],
            ['Returns', 'returns.index', 'return', ['admin', 'manager']],
        ],
        'Website' => [
            ['Car Requests', 'website.requests', 'car', ['admin', 'manager', 'staff']],
            ['Import Orders', 'imports.index', 'truck', ['admin', 'manager', 'staff']],
            ['Banners', 'website.banners', 'image', ['admin', 'manager']],
            ['Car List', 'website.cars', 'list', ['admin', 'manager']],
            ['Website Texts', 'website.texts', 'type', ['admin', 'manager']],
        ],
        'Finance' => [
            ['Invoices & Payments', 'invoices.index', 'doc', ['admin', 'manager', 'staff']],
            ['Reports', 'reports.index', 'chart', ['admin', 'manager']],
        ],
        'Administration' => [
            ['Users', 'users.index', 'shield', ['admin']],
            ['Lists', 'lists.index', 'list', ['admin', 'manager']],
            ['Settings', 'settings', 'cog', ['admin']],
            ['Audit Log', 'audit.index', 'clock', ['admin']],
            ['Import Data', 'import.index', 'upload', ['admin']],
        ],
    ];
    $newOrders = $user->hasRole('admin', 'manager', 'staff') ? \App\Models\Order::where('status', 'pending')->count() : 0;
    $newRequests = $user->hasRole('admin', 'manager', 'staff') ? \App\Models\CarRequest::where('status', 'new')->count() : 0;
    $newImports = $user->hasRole('admin', 'manager', 'staff') ? \App\Models\ImportRequest::where('status', 'new')->count() : 0;
@endphp
<body class="min-h-screen" x-data="{ sidebar: false }">
<div class="flex min-h-screen">
    {{-- ================= SIDEBAR ================= --}}
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink-950/50 lg:hidden" @click="sidebar = false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 print:hidden -translate-x-full flex-col bg-ink-950 transition-transform lg:translate-x-0"
           :class="sidebar && '!translate-x-0'">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex h-16 items-center border-b border-white/5 px-3">
            <span class="flex h-12 w-full items-center justify-center rounded-lg bg-white px-3">
                <img src="{{ logo_url() }}" alt="{{ setting('business_name') }}" class="max-h-10 w-full object-contain">
            </span>
        </a>

        <nav class="flex-1 overflow-y-auto px-3 pb-6">
            @foreach ($nav as $section => $items)
                @php $visible = array_filter($items, fn ($i) => $user->hasRole(...$i[3])); @endphp
                @if ($visible)
                    <div class="nav-section">{{ $section }}</div>
                    @foreach ($visible as [$label, $route, $icon])
                        @if ($route)
                            <a href="{{ route(str_replace('.*', '.index', $route)) }}" wire:navigate class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}">
                                <x-icon :name="$icon" class="h-[18px] w-[18px]" /> {{ $label }}
                                @if ($route === 'imports.index' && $newImports) <span class="ml-auto rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $newImports }}</span> @endif
                                @if ($route === 'website.requests' && $newRequests) <span class="ml-auto rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $newRequests }}</span> @endif
                                @if ($route === 'orders.index' && $newOrders) <span class="ml-auto rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $newOrders }}</span> @endif
                            </a>
                        @else
                            <span class="nav-soon" title="Coming in the next phase">
                                <x-icon :name="$icon" class="h-[18px] w-[18px]" /> {{ $label }}
                                <span class="ml-auto rounded bg-white/5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-ink-500">Soon</span>
                            </span>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </nav>

        <div class="border-t border-white/5 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-white">{{ $user->initials() }}</span>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-sm font-semibold text-white">{{ $user->name }}</div>
                    <div class="text-[11px] text-ink-400">{{ $user->roleLabel() }}</div>
                </div>
                <a href="{{ route('password.change') }}" wire:navigate class="btn-icon !text-ink-400 hover:!bg-white/5 hover:!text-white" title="Change password"><x-icon name="key" class="h-4 w-4" /></a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn-icon !text-ink-400 hover:!bg-white/5 hover:!text-white" title="Sign out"><x-icon name="logout" class="h-4 w-4" /></button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ================= MAIN ================= --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64 print:pl-0">
        <header class="sticky top-0 z-20 flex h-16 print:hidden items-center gap-3 border-b border-ink-100 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button class="btn-icon lg:hidden" @click="sidebar = true"><x-icon name="menu" /></button>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-bold text-ink-900">{{ $title ?? 'Dashboard' }}</h1>
            </div>
            <div class="ml-auto text-right text-xs text-ink-400">
                <div class="font-semibold text-ink-600">{{ now()->format('l, d M Y') }}</div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 print:p-0">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- ================= TOASTS ================= --}}
<div x-data="{ items: [], add(d) { const id = Date.now() + Math.random(); this.items.push({ id, ...d }); setTimeout(() => this.items = this.items.filter(i => i.id !== id), d.type === 'warning' ? 9000 : 4000) } }"
     x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
     @toast.window="add($event.detail)"
     class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-80 flex-col gap-2">
    <template x-for="t in items" :key="t.id">
        <div x-transition class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-lg"
             :class="t.type === 'error' ? 'border-brand-200' : (t.type === 'warning' ? 'border-amber-300 bg-amber-50' : 'border-ink-100')">
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-white"
                  :class="t.type === 'error' ? 'bg-brand-500' : (t.type === 'warning' ? 'bg-amber-500' : 'bg-emerald-500')">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path :d="t.type === 'error' ? 'M6 6l12 12M18 6 6 18' : (t.type === 'warning' ? 'M12 7v6M12 17h.01' : 'M5 12.5 10 17l9-10')"/></svg>
            </span>
            <p class="text-sm font-medium text-ink-800" x-text="t.message"></p>
        </div>
    </template>
</div>
@livewireScripts
</body>
</html>
