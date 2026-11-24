<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $inv->number }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Courier New", Consolas, monospace; font-size: 12px; color: #000; background: #eee; }
        .r { width: 74mm; margin: 12px auto; background: #fff; padding: 4mm 3mm; }
        @media print { body { background: #fff; } .r { margin: 0; width: auto; padding: 0; } .no-print { display: none; } }
        .c { text-align: center; } .b { font-weight: 700; }
        img { max-width: 40mm; max-height: 18mm; display: block; margin: 0 auto 2mm; filter: grayscale(1) contrast(1.4); }
        .name { font-size: 15px; font-weight: 800; letter-spacing: .5px; }
        .small { font-size: 10px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 2.5mm 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: .6mm 0; vertical-align: top; }
        td.n { text-align: right; white-space: nowrap; }
        .total td { font-size: 15px; font-weight: 800; padding-top: 1.5mm; }
        .toolbar { text-align: center; margin: 12px; }
        .toolbar button { font: 600 13px sans-serif; padding: 8px 14px; border-radius: 6px; border: 1px solid #999; background: #fff; cursor: pointer; }
    </style>
</head>
<body>
<div class="toolbar no-print"><button onclick="window.close()">Close</button> <button onclick="window.print()">Print</button></div>
<div class="r">
    <img src="{{ logo_url() }}" alt="">
    <div class="c name">{{ setting('business_name') }}</div>
    <div class="c small">Spare Parts &amp; Accessories · Garage Services</div>
    @if (setting('address')) <div class="c small">{{ setting('address') }}</div> @endif
    @if (setting('phone')) <div class="c small">Tel: {{ setting('phone') }}</div> @endif
    @if (setting('tin')) <div class="c small">TIN: {{ setting('tin') }} {{ setting('vrn') ? '· VRN: '.setting('vrn') : '' }}</div> @endif
    <hr>
    <div class="c b">{{ $inv->status === 'cancelled' ? '*** VOIDED ***' : 'SALES RECEIPT' }}</div>
    <table class="small">
        <tr><td>No:</td><td class="n b">{{ $inv->number }}</td></tr>
        <tr><td>Date:</td><td class="n">{{ $inv->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Customer:</td><td class="n">{{ $inv->customerName() }}</td></tr>
        <tr><td>Served by:</td><td class="n">{{ $inv->creator?->name }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach ($inv->items as $i)
            <tr><td colspan="2">{{ $i->description }}</td></tr>
            <tr class="small"><td>&nbsp; {{ $i->qtyLabel() }} x {{ amount($i->rate) }}</td><td class="n">{{ amount($i->amount) }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Subtotal</td><td class="n">{{ amount($inv->subtotal) }}</td></tr>
        @if ((float) $inv->discount) <tr><td>Discount</td><td class="n">-{{ amount($inv->discount) }}</td></tr> @endif
        @if ((float) $inv->tax_amount) <tr><td>VAT</td><td class="n">{{ amount($inv->tax_amount) }}</td></tr> @endif
        <tr class="total"><td>TOTAL {{ setting('currency') }}</td><td class="n">{{ amount($inv->grand_total) }}</td></tr>
        @foreach ($inv->payments as $p)
            <tr class="small"><td>{{ (float) $p->amount < 0 ? 'Refund' : 'Paid' }} ({{ $p->mode }}{{ $p->reference ? ' '.$p->reference : '' }})</td><td class="n">{{ amount($p->amount) }}</td></tr>
        @endforeach
        @if ((float) $inv->credited) <tr class="small"><td>Returns</td><td class="n">-{{ amount($inv->credited) }}</td></tr> @endif
        @if ((float) $inv->balance > 0 && $inv->status !== 'cancelled') <tr class="b"><td>BALANCE DUE</td><td class="n">{{ amount($inv->balance) }}</td></tr> @endif
    </table>
    <hr>
    <div class="small">
        @foreach (preg_split('/\r\n|\r|\n/', (string) setting('payment_details')) as $row) @if (trim($row) !== '') <div>{{ $row }}</div> @endif @endforeach
    </div>
    <hr>
    <div class="c small b">{{ setting('invoice_footer') }}</div>
    <div class="c small">Goods sold are returnable only with this receipt.</div>
</div>
@unless (request()->has('noprint'))
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endunless
</body>
</html>
