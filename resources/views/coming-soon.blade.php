@php
    $wa = setting('whatsapp') ?: setting('phone');
    $waLink = $wa ? 'https://wa.me/'.\App\Support\Phone::international($wa) : null;
    $left = max(0, now()->diffInSeconds($launch, false));
    $G = (int) $launch->format('G');
    $swHour = (($G - 6) % 12 + 12) % 12 ?: 12;          // Swahili time counts from 6 o'clock
    $swPart = $G < 12 ? 'asubuhi' : ($G < 16 ? 'mchana' : ($G < 19 ? 'jioni' : 'usiku'));
    $swDay = ['Monday' => 'Jumatatu', 'Tuesday' => 'Jumanne', 'Wednesday' => 'Jumatano', 'Thursday' => 'Alhamisi', 'Friday' => 'Ijumaa', 'Saturday' => 'Jumamosi', 'Sunday' => 'Jumapili'][$launch->format('l')];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Coming soon | {{ setting('business_name') }}</title>
    <meta name="description" content="{{ setting('business_name') }} opens {{ $launch->format('l j F, g:i A') }}. Spare parts shop and garage.">
    <link rel="icon" href="{{ asset('favicon.ico').'?v=2' }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png').'?v=2' }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png').'?v=2' }}">
    <meta property="og:title" content="{{ setting('business_name') }} opens {{ $launch->format('j F, g:i A') }}">
    <meta property="og:image" content="{{ asset('images/soon/hero.jpg') }}">
    <link rel="preload" as="image" href="{{ asset('images/soon/hero.jpg') }}">
    @vite(['resources/css/shop.css'])
    <style>
        body { background: #050506; }
        /* photo backdrop, slowly zooming */
        .cs-hero { position: absolute; inset: 0; background: url('{{ asset('images/soon/hero.jpg') }}') 70% 40% / cover no-repeat; animation: kenburns 28s ease-in-out infinite alternate; filter: saturate(1.1) brightness(.62); }
        @keyframes kenburns { from { transform: scale(1.04) translateX(0); } to { transform: scale(1.14) translateX(-2%); } }
        .cs-shade { position: absolute; inset: 0; background:
            linear-gradient(90deg, #050506 0%, #050506 22%, rgba(5,5,6,.97) 38%, rgba(5,5,6,.6) 56%, rgba(5,5,6,.15) 75%, rgba(5,5,6,.35) 100%),
            linear-gradient(180deg, rgba(5,5,6,.65) 0%, transparent 25%, transparent 60%, #050506 100%); }
        /* red light sweeping across */
        .cs-sweep { position: absolute; left: -40%; top: 62%; width: 60%; height: 3px; background: linear-gradient(90deg, transparent, #ff1f2b 50%, transparent); filter: drop-shadow(0 0 8px #ff1f2b); transform: rotate(-7deg); animation: sweep 5.5s cubic-bezier(.6,.05,.3,1) infinite; opacity: .9; }
        .cs-sweep.two { top: 90%; height: 2px; animation-delay: 2.7s; animation-duration: 7s; }
        @keyframes sweep { 0% { left: -60%; } 60%, 100% { left: 130%; } }
        /* blinking "coming soon" dot */
        .cs-dot { width: 10px; height: 10px; border-radius: 9999px; background: #ff2a35; box-shadow: 0 0 0 0 rgba(255,42,53,.7); animation: ping 1.4s ease-out infinite; }
        @keyframes ping { 0% { box-shadow: 0 0 0 0 rgba(255,42,53,.7); opacity: 1; } 70% { box-shadow: 0 0 0 12px rgba(255,42,53,0); opacity: .35; } 100% { opacity: 1; } }
        .cs-title span { background: linear-gradient(90deg, #ff3b45, #ff7a45, #ff3b45); background-size: 200% 100%; -webkit-background-clip: text; background-clip: text; color: transparent; animation: shine 4s linear infinite; }
        @keyframes shine { to { background-position: -200% 0; } }
        /* rings and sparks */
        .cs-ring { position: absolute; border-radius: 9999px; border: 2px solid rgba(255,40,50,.55); box-shadow: 0 0 40px rgba(255,30,40,.25) inset, 0 0 30px rgba(255,30,40,.2); animation: ring 4.8s ease-out infinite; }
        @keyframes ring { 0% { transform: scale(.35); opacity: 0; } 25% { opacity: 1; } 100% { transform: scale(1.25); opacity: 0; } }
        .cs-spark { position: absolute; bottom: -10px; width: 3px; height: 3px; border-radius: 9999px; background: #ff4a52; box-shadow: 0 0 8px 2px rgba(255,60,70,.7); animation: spark 9s linear infinite; }
        @keyframes spark { 0% { transform: translateY(0) translateX(0); opacity: 0; } 10% { opacity: 1; } 100% { transform: translateY(-105vh) translateX(40px); opacity: 0; } }
        /* countdown */
        .cs-box { background: linear-gradient(180deg, rgba(255,255,255,.09), rgba(255,255,255,.02)); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .cs-num { font-variant-numeric: tabular-nums; display: inline-block; }
        .cs-num.tick { animation: tick .45s cubic-bezier(.2,.9,.3,1.2); }
        @keyframes tick { from { transform: translateY(-35%); opacity: 0; } to { transform: none; opacity: 1; } }
        .cs-in { animation: rise .9s cubic-bezier(.2,.8,.2,1) both; }
        @keyframes rise { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; } }
    </style>
    @include('partials.analytics')
</head>
<body class="min-h-screen overflow-x-hidden font-sans text-white antialiased">
    <div class="relative flex min-h-screen flex-col overflow-hidden">
        <div class="cs-hero" aria-hidden="true"></div>
        <div class="cs-shade" aria-hidden="true"></div>
        <div class="cs-sweep" aria-hidden="true"></div>
        <div class="cs-sweep two" aria-hidden="true"></div>

        {{-- pulsing red rings and rising sparks on the right --}}
        <div class="pointer-events-none absolute inset-0 hidden lg:block" aria-hidden="true">
            <div class="cs-ring" style="right:12%;top:22%;width:440px;height:440px"></div>
            <div class="cs-ring" style="right:12%;top:22%;width:440px;height:440px;animation-delay:-1.6s"></div>
            <div class="cs-ring" style="right:12%;top:22%;width:440px;height:440px;animation-delay:-3.2s"></div>
            @for ($i = 0; $i < 18; $i++)
                <span class="cs-spark" style="left:{{ 52 + ($i * 37) % 46 }}%;animation-delay:-{{ ($i * 0.73) % 9 }}s;animation-duration:{{ 7 + ($i * 1.3) % 6 }}s"></span>
            @endfor
        </div>

        <header class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 pt-6 sm:px-8 lg:pt-8">
            <img src="{{ logo_dark_url() ?? logo_url() }}" alt="{{ setting('business_name') }}" class="h-12 w-auto sm:h-14">
            @if ($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="rounded-md border border-white/20 bg-white/5 px-4 py-2 text-sm font-semibold backdrop-blur hover:bg-white/15">WhatsApp us</a>
            @endif
        </header>

        <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 items-center px-5 py-10 sm:px-8">
            <div class="max-w-xl">
                <p class="cs-in flex items-center gap-3 text-sm font-bold tracking-[0.3em] text-white/85"><span class="cs-dot"></span>COMING SOON</p>
                <h1 class="cs-in cs-title mt-4 font-display text-5xl font-extrabold leading-[0.95] tracking-tight sm:text-7xl" style="animation-delay:.1s">Be ready for<br><span>exciting new things.</span></h1>
                <p class="cs-in mt-5 text-lg text-white/75" style="animation-delay:.2s">Spare parts shop and garage, all in one place. We open <b class="text-white">{{ $launch->format('l j F') }}</b> at <b class="text-white">{{ $launch->format('g:i A') }}</b>.</p>
                <p class="cs-in mt-1 text-white/55" style="animation-delay:.25s">Tunafungua {{ $swDay }} {{ $launch->format('j/n') }}, saa {{ $swHour }}{{ $launch->format(':i') !== ':00' ? $launch->format(':i') : '' }} {{ $swPart }}. Kaa tayari!</p>

                {{-- countdown from the server's time; opens the website by itself at zero --}}
                <div id="cd" data-left="{{ $left }}" class="cs-in mt-9 grid max-w-md grid-cols-4 gap-3" style="animation-delay:.35s" aria-live="polite">
                    @foreach (['d' => 'Days', 'h' => 'Hours', 'm' => 'Minutes', 's' => 'Seconds'] as $k => $label)
                        <div class="cs-box overflow-hidden rounded-xl border border-white/10 px-2 py-4 text-center">
                            <div class="h-12 overflow-hidden sm:h-14"><span data-u="{{ $k }}" class="cs-num font-display text-4xl font-extrabold sm:text-5xl">00</span></div>
                            <div class="mt-1 text-[11px] font-semibold uppercase tracking-wider text-white/50">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cs-in mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/60" style="animation-delay:.45s">
                    @if (setting('address')) <span>{{ setting('address') }}</span> @endif
                    @if (setting('phone')) <a href="tel:{{ preg_replace('/\s+/', '', setting('phone')) }}" class="font-semibold text-white hover:underline">{{ setting('phone') }}</a> @endif
                    <span>{{ opening_hours() }}</span>
                </div>
            </div>
        </main>

    </div>

    <script>
        (function () {
            var el = document.getElementById('cd');
            var end = Date.now() + parseInt(el.dataset.left, 10) * 1000;
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            var last = {};
            function set(u, v) {
                if (last[u] === v) return;
                last[u] = v;
                var n = el.querySelector('[data-u=' + u + ']');
                n.textContent = v; n.classList.remove('tick'); void n.offsetWidth; n.classList.add('tick');
            }
            function tick() {
                var s = Math.max(0, Math.round((end - Date.now()) / 1000));
                set('d', pad(Math.floor(s / 86400))); set('h', pad(Math.floor(s % 86400 / 3600)));
                set('m', pad(Math.floor(s % 3600 / 60))); set('s', pad(s % 60));
                if (s <= 0) { clearInterval(t); setTimeout(function () { location.reload(); }, 1500); }
            }
            var t = setInterval(tick, 1000); tick();
        })();
    </script>
</body>
</html>
