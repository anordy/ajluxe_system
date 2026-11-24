<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $docTitle ?? $title }} — {{ setting('business_name') }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: #111; font-size: 12px; line-height: 1.45; background: #e9e9ea; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .page { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 14mm 14mm 12mm; position: relative; box-shadow: 0 2px 18px rgba(0,0,0,.12); display: flex; flex-direction: column; }
        @media print { body { background: #fff; } .page { margin: 0; width: auto; min-height: auto; padding: 0; box-shadow: none; } .no-print { display: none !important; } }

        .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { font: 600 13px/1 inherit; padding: 9px 16px; border-radius: 8px; border: 1px solid #ccc; background: #fff; cursor: pointer; }
        .toolbar button.dark { background: #111; color: #fff; border-color: #111; }

        /* header */
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .brand { display: flex; gap: 14px; align-items: center; max-width: 62%; }
        .brand img { height: 74px; width: auto; object-fit: contain; }
        .brand .name { font-size: 20px; font-weight: 800; letter-spacing: .5px; margin: 0; }
        .brand .tag { font-size: 10px; color: #555; margin: 3px 0 5px; line-height: 1.35; }
        .brand .contact { font-size: 10.5px; color: #333; }
        .doc { text-align: right; }
        .doc h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1.5px; color: #111; line-height: 1.1; }
        .doc .meta { margin-top: 8px; font-size: 11.5px; }
        .doc .meta td { padding: 1px 0 1px 12px; text-align: right; }
        .doc .meta td:first-child { color: #666; }
        .doc .meta b { font-family: Consolas, "Courier New", monospace; font-size: 12px; }
        .bar { height: 4px; margin: 14px 0 16px; background: linear-gradient(90deg, #E31B23 0, #E31B23 22%, #111 22%, #111 100%); border-radius: 2px; }

        /* info blocks */
        .info { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
        .info.three { grid-template-columns: 1fr 1fr 1fr; }
        .box { border: 1px solid #e2e2e2; border-radius: 6px; padding: 10px 12px; }
        .box h4 { margin: 0 0 6px; font-size: 9.5px; letter-spacing: 1.2px; text-transform: uppercase; color: #E31B23; font-weight: 700; }
        .box .big { font-size: 13.5px; font-weight: 700; }
        .box table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .box td { padding: 1px 0; vertical-align: top; }
        .box td:first-child { color: #666; width: 42%; }
        .plate { display: inline-block; border: 2px solid #111; border-radius: 4px; padding: 1px 7px; font-family: Consolas, monospace; font-weight: 800; letter-spacing: 1px; background: #fde68a; }

        /* items */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th { background: #f1f1f2; color: #333; font-size: 10px; text-transform: uppercase; letter-spacing: .8px; text-align: left; padding: 8px 9px; border-bottom: 1px solid #ddd; }
        table.items td { padding: 8px 9px; border-bottom: 1px solid #eee; vertical-align: top; }
        table.items .r { text-align: right; white-space: nowrap; }
        table.items .c { text-align: center; }
        table.items .muted { color: #888; font-size: 10px; text-transform: uppercase; letter-spacing: .6px; }
        table.items tr.group td { background: #fafafa; font-weight: 700; font-size: 10.5px; letter-spacing: .6px; text-transform: uppercase; color: #444; padding: 6px 9px; }

        /* totals */
        .bottom { display: grid; grid-template-columns: 1fr 260px; gap: 22px; align-items: start; }
        table.totals { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.totals td { padding: 5px 0; }
        table.totals td:last-child { text-align: right; font-weight: 600; white-space: nowrap; }
        table.totals tr.grand td { border-top: 2px solid #111; border-bottom: 2px solid #111; padding: 8px 0; font-size: 15px; font-weight: 800; color: #111; }
        table.totals tr.due td { font-weight: 800; color: #E31B23; }
        table.totals tr.settled td { font-weight: 800; color: #111; }
        .words { font-size: 11px; color: #444; margin-top: 8px; font-style: italic; }
        .pay { background: #f6f6f7; border-radius: 6px; padding: 10px 12px; font-size: 11.5px; }
        .pay h4 { margin: 0 0 5px; font-size: 9.5px; letter-spacing: 1.2px; text-transform: uppercase; color: #111; }
        .pay div { font-family: Consolas, "Courier New", monospace; font-weight: 600; padding: 1px 0; }
        .note { font-size: 11px; color: #444; margin-top: 10px; white-space: pre-line; }

        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 36px; }
        .sign div { border-top: 1px solid #111; padding-top: 5px; font-size: 10.5px; color: #444; }
        .stamp { position: absolute; top: 46%; left: 50%; transform: translate(-50%, -50%) rotate(-18deg); font-size: 64px; font-weight: 900; letter-spacing: 6px; opacity: .07; pointer-events: none; }
        .foot { margin-top: auto; padding-top: 18px; text-align: center; font-size: 10.5px; color: #666; }
        .foot .thanks { font-weight: 700; color: #111; font-size: 11.5px; margin-bottom: 3px; }
        .foot .line { height: 2px; background: #111; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button onclick="window.close()">Close</button>
        <button class="dark" onclick="window.print()">Print</button>
    </div>
    <div class="page">
        <div class="head">
            <div class="brand">
                <img src="{{ logo_url() }}" alt="">
                <div>
                    <p class="name">{{ setting('business_name') }}</p>
                    <p class="tag">{{ setting('business_tagline') }}</p>
                    <div class="contact">
                        @if (setting('address')) {{ setting('address') }}<br> @endif
                        {{ collect([setting('phone') ? 'Tel: '.setting('phone') : null, setting('email')])->filter()->implode('  ·  ') }}
                        @if (setting('tin') || setting('vrn'))<br>{{ collect([setting('tin') ? 'TIN: '.setting('tin') : null, setting('vrn') ? 'VRN: '.setting('vrn') : null])->filter()->implode('  ·  ') }}@endif
                    </div>
                </div>
            </div>
            <div class="doc">
                <h1>{{ $title }}</h1>
                <table class="meta">@yield('meta')</table>
            </div>
        </div>
        <div class="bar"></div>

        @yield('content')

        <div class="foot">
            <div class="line"></div>
            <div class="thanks">{{ setting('invoice_footer') }}</div>
            {{ setting('business_name') }} — {{ setting('business_tagline') }}
        </div>
    </div>
    @unless (request()->has('noprint'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endunless
</body>
</html>
