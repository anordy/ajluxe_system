<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} · {{ setting('business_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico').'?v=2' }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png').'?v=2' }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ asset('icon-192.png').'?v=2' }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png').'?v=2' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-white">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- brand panel --}}
        <div class="relative hidden overflow-hidden bg-ink-950 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full border-[40px] border-brand-500/10"></div>
            <div class="absolute -bottom-32 -left-20 h-[28rem] w-[28rem] rounded-full border-[48px] border-white/[0.03]"></div>
            <div class="relative">
                @if (logo_dark_url()) <img src="{{ logo_dark_url() }}" alt="{{ setting('business_name') }}" class="h-16 w-auto"> @else <span class="inline-flex rounded-xl bg-white px-4 py-3"><img src="{{ logo_url() }}" alt="{{ setting('business_name') }}" class="h-12"></span> @endif
            </div>
            <div class="relative max-w-md">
                <div class="mb-4 h-1 w-16 rounded bg-brand-500"></div>
                <h2 class="text-3xl font-extrabold leading-tight text-white">Garage &amp; Spare Parts<br>Management System</h2>
                <p class="mt-4 text-sm leading-relaxed text-ink-400">{{ setting('business_tagline') }}</p>
            </div>
            <p class="relative text-xs text-ink-500">&copy; {{ date('Y') }} {{ setting('business_name') }}</p>
        </div>

        {{-- form panel --}}
        <div class="flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 lg:hidden"><img src="{{ logo_url() }}" alt="{{ setting('business_name') }}" class="h-12"></div>
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
