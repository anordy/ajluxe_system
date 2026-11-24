@php
    use App\Services\Reports;
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $stat = fn ($label, $value, $sub = null, $tone = '') => compact('label', 'value', 'sub', 'tone');
@endphp
<div class="space-y-5">
    {{-- report picker --}}
    <div class="flex gap-2 overflow-x-auto pb-1 print:hidden">
        @foreach (Reports::REPORTS as $key => $label)
            <button wire:click="$set('report', @js($key))"
                    class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition {{ $report === $key ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-100 hover:bg-ink-50' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- filters --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center print:hidden">
        @if ($dated)
            <select wire:model.live="range" class="input lg:max-w-[170px]">
                @foreach (\App\Livewire\Reports\Index::RANGES as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
            </select>
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="from" class="input lg:w-40"><span class="text-ink-400">–</span><input type="date" wire:model.live="to" class="input lg:w-40">
            </div>
        @else
            <p class="text-sm text-ink-500">As of today, {{ now()->format('d M Y') }}</p>
        @endif
        <div class="flex gap-2 lg:ml-auto">
            <a href="{{ route('reports.export', ['report' => $report, 'from' => $fromDate, 'to' => $toDate]) }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Export CSV</a>
            <button onclick="window.print()" class="btn-light"><x-icon name="printer" class="h-4 w-4" /> Print</button>
        </div>
    </div>

    <div class="hidden print:block">
        <h1 class="text-xl font-extrabold">{{ setting('business_name') }} — {{ Reports::REPORTS[$report] }}</h1>
        <p class="text-sm">{{ $dated ? $period : 'As of '.now()->format('d M Y') }} · printed {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div wire:loading.class="opacity-50" class="space-y-5 transition">
    {{-- =============================================================== SALES --}}
    @if ($report === 'sales')
        @php $g = $data['garage']; $c = $data['counter']; @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                $stat('Total sales', money($data['total']), ($g['count'] + $c['count']).' invoices'),
                $stat('Garage', money($g['total']), $g['count'].' job invoices'),
                $stat('Spare parts', money($c['total']), $c['count'].' sales'),
                $stat('Still unpaid from these', money($data['unpaid']), $data['returns'] ? 'Returns: − '.money($data['returns']) : null, $data['unpaid'] > 0 ? 'text-brand-600' : ''),
            ] as $s)
                <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $s['label'] }}</p><p class="mt-1 text-2xl font-extrabold {{ $s['tone'] }}">{{ $s['value'] }}</p>@if ($s['sub']) <p class="text-xs text-ink-400">{{ $s['sub'] }}</p> @endif</div>
            @endforeach
        </div>
        <div class="card p-5">
            <h3 class="card-title mb-4">Sales by {{ count($data['daily']) && strlen($data['daily'][0]['date']) === 7 ? 'month' : 'day' }} — {{ $period }}</h3>
            <x-column-chart :data="$data['daily']" />
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h3 class="card-title">Breakdown</h3></div>
            <table class="table">
                <thead><tr><th></th><th class="text-right">Labour</th><th class="text-right">Parts</th><th class="text-right">Other</th><th class="text-right">Discount</th><th class="text-right">Tax</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                @foreach (['Garage' => $g, 'Spare parts' => $c] as $label => $row)
                    <tr><td class="font-semibold">{{ $label }}</td><td class="text-right">{{ amount($row['labour']) }}</td><td class="text-right">{{ amount($row['parts']) }}</td><td class="text-right">{{ amount($row['other']) }}</td>
                        <td class="text-right">− {{ amount($row['discount']) }}</td><td class="text-right">{{ amount($row['tax']) }}</td><td class="text-right font-bold">{{ amount($row['total']) }}</td></tr>
                @endforeach
                <tr class="bg-ink-50"><td class="font-bold">Total</td><td class="text-right font-semibold">{{ amount($g['labour'] + $c['labour']) }}</td><td class="text-right font-semibold">{{ amount($g['parts'] + $c['parts']) }}</td><td class="text-right font-semibold">{{ amount($g['other'] + $c['other']) }}</td>
                    <td class="text-right">− {{ amount($g['discount'] + $c['discount']) }}</td><td class="text-right">{{ amount($g['tax'] + $c['tax']) }}</td><td class="text-right font-extrabold">{{ amount($data['total']) }}</td></tr>
                @if ($data['returns'] > 0)
                    <tr><td>Customer returns (credit notes)</td><td colspan="5"></td><td class="text-right">− {{ amount($data['returns']) }}</td></tr>
                    <tr><td class="font-bold">Net sales</td><td colspan="5"></td><td class="text-right font-extrabold">{{ amount($data['net']) }}</td></tr>
                @endif
                </tbody>
            </table>
        </div>

    {{-- =============================================================== PROFIT --}}
    @elseif ($report === 'profit')
        @php $c = $data['collected']; @endphp
        <div class="card p-5">
            <h3 class="card-title">Profit collected</h3>
            <p class="mb-4 mt-0.5 text-xs text-ink-400">Counted when customers pay. Each payment brings in its share of the invoice's profit; unpaid invoices add nothing until they are paid.</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div><p class="text-xs text-ink-400">Money collected</p><p class="text-xl font-extrabold tabular-nums">{{ money($c['money']) }}</p></div>
                <div><p class="text-xs text-ink-400">Profit collected</p><p class="text-xl font-extrabold tabular-nums {{ $c['profit'] < 0 ? 'text-brand-600' : '' }}">{{ money($c['profit']) }}</p><p class="text-xs text-ink-400">{{ $c['margin'] }}%</p></div>
                @foreach (\App\Services\Analytics::CHANNELS as $k => $label)
                    <div><p class="text-xs text-ink-400">{{ $label }}</p><p class="text-lg font-bold tabular-nums">{{ money($c['profitBy'][$k]) }}</p><p class="text-xs text-ink-400">from {{ money($c['moneyBy'][$k]) }}</p></div>
                @endforeach
            </div>
            <p class="hint mt-4">Still to collect: {{ money($c['owedTotal']) }}, holding {{ money($c['owedProfit']) }} profit.</p>
        </div>
        <h3 class="pt-2 text-sm font-semibold text-ink-500">Earned on invoices dated in this period (paid or not)</h3>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Revenue (excl. tax)</p><p class="mt-1 text-2xl font-extrabold">{{ money($data['revenue']) }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Cost of parts &amp; outside work</p><p class="mt-1 text-2xl font-extrabold">{{ money($data['cost']) }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Gross profit</p><p class="mt-1 text-2xl font-extrabold {{ $data['profit'] < 0 ? 'text-brand-600' : '' }}">{{ money($data['profit']) }}</p><p class="text-xs text-ink-400">{{ $data['margin'] }}% margin</p></div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">By business</h3></div>
                <table class="table">
                    <thead><tr><th></th><th class="text-right">Revenue</th><th class="text-right">Cost</th><th class="text-right">Profit</th><th class="text-right">Margin</th></tr></thead>
                    <tbody>
                    @foreach ($data['rows'] as $row)
                        <tr><td class="font-semibold">{{ $row['label'] }} @if (! empty($row['internal'])) <div class="text-[11px] font-normal text-ink-400">Parts used on garage jobs, at the garage's cost</div> @endif</td><td class="text-right">{{ amount($row['revenue']) }}</td><td class="text-right">{{ amount($row['cost']) }}</td><td class="text-right font-bold">{{ amount($row['profit']) }}</td><td class="text-right">{{ $row['margin'] }}%</td></tr>
                    @endforeach
                    @if ($data['returns']['revenue'] > 0)
                        <tr><td>Customer returns</td><td class="text-right">− {{ amount($data['returns']['revenue']) }}</td><td class="text-right">− {{ amount($data['returns']['cost']) }}</td><td class="text-right">− {{ amount($data['returns']['revenue'] - $data['returns']['cost']) }}</td><td></td></tr>
                    @endif
                    <tr class="bg-ink-50"><td class="font-bold">Whole business <div class="text-[11px] font-normal text-ink-400">Parts supplied to the garage are counted once</div></td><td class="text-right font-semibold">{{ amount($data['revenue']) }}</td><td class="text-right font-semibold">{{ amount($data['cost']) }}</td><td class="text-right font-extrabold">{{ amount($data['profit']) }}</td><td class="text-right font-semibold">{{ $data['margin'] }}%</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Garage profit by type of work</h3></div>
                @php $maxP = max(1, collect($data['garageMix'])->max('profit') ?? 1); @endphp
                <ul class="space-y-4 p-5">
                    @forelse ($data['garageMix'] as $m)
                        <li>
                            <div class="flex justify-between text-sm"><span class="font-semibold">{{ $m['kind'] }}</span><span><b>{{ money($m['profit']) }}</b> <span class="text-ink-400">from {{ money($m['revenue']) }}</span></span></div>
                            <div class="mt-1.5 h-2 rounded-full bg-ink-100"><div class="h-2 rounded-full" style="width: {{ max(0, $m['profit']) / $maxP * 100 }}%; background: #2a78d6"></div></div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-ink-400">No garage invoices in this period.</li>
                    @endforelse
                </ul>
                <p class="hint px-5 pb-4">Garage profit = selling price − cost on each line. Parts cost the garage the spare-parts selling price (the shop's margin is shown in the row "Spare parts — supplied to garage"). Labour usually has no cost.</p>
            </div>
        </div>

    {{-- =============================================================== PAYMENTS --}}
    @elseif ($report === 'payments')
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Received from customers (net)</p><p class="mt-1 text-2xl font-extrabold">{{ money($data['totalIn']) }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Paid to suppliers</p><p class="mt-1 text-2xl font-extrabold">{{ money($data['totalOut']) }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Net cash flow</p><p class="mt-1 text-2xl font-extrabold {{ $data['net'] < 0 ? 'text-brand-600' : '' }}">{{ money($data['net']) }}</p></div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Money in — by payment method</h3></div>
                <table class="table">
                    <thead><tr><th>Method</th><th class="text-right">Payments</th><th class="text-right">Received</th><th class="text-right">Refunded</th><th class="text-right">Net</th></tr></thead>
                    <tbody>
                    @forelse ($data['in'] as $m)
                        <tr><td class="font-semibold">{{ $m['mode'] }}</td><td class="text-right">{{ $m['count'] }}</td><td class="text-right">{{ amount($m['received']) }}</td><td class="text-right">{{ $m['refunded'] ? '− '.amount($m['refunded']) : '—' }}</td><td class="text-right font-bold">{{ amount($m['net']) }}</td></tr>
                    @empty <tr><td colspan="5" class="py-8 text-center text-ink-400">No payments in this period.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Money out — supplier payments</h3></div>
                <table class="table">
                    <thead><tr><th>Method</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @forelse ($data['out'] as $m)
                        <tr><td class="font-semibold">{{ $m['mode'] }}</td><td class="text-right">{{ $m['count'] }}</td><td class="text-right font-bold">{{ amount($m['amount']) }}</td></tr>
                    @empty <tr><td colspan="3" class="py-8 text-center text-ink-400">No supplier payments in this period.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($data['days'])
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Daily collections (for cash-up)</h3></div>
                <table class="table">
                    <thead><tr><th>Date</th><th class="text-right">Net received</th></tr></thead>
                    <tbody>@foreach ($data['days'] as $d) <tr><td>{{ \Carbon\Carbon::parse($d['date'])->format('D, d M Y') }}</td><td class="text-right font-semibold">{{ amount($d['amount']) }}</td></tr> @endforeach</tbody>
                </table>
            </div>
        @endif

    {{-- =============================================================== RECEIVABLES / PAYABLES --}}
    @elseif (in_array($report, ['receivables', 'payables']))
        @php $isR = $report === 'receivables'; $t = $data['totals']; @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach (['total' => 'Total owed', 'd30' => '0–30 days', 'd60' => '31–60 days', 'd90' => '61–90 days', 'older' => 'Over 90 days'] as $k => $label)
                <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $label }}</p>
                    <p class="mt-1 text-xl font-extrabold {{ $k === 'older' && $t[$k] > 0 ? 'text-brand-600' : '' }}">{{ money($t[$k]) }}</p></div>
            @endforeach
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h3 class="card-title">{{ $isR ? 'Customers who owe you' : 'Suppliers you owe' }}</h3></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>{{ $isR ? 'Customer' : 'Supplier' }}</th><th>Phone</th><th class="text-right">{{ $isR ? 'Invoices' : 'Orders' }}</th><th class="text-right">0–30</th><th class="text-right">31–60</th><th class="text-right">61–90</th><th class="text-right">90+</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    @forelse ($data['rows'] as $r)
                        <tr><td class="font-semibold">{{ $r['name'] }}</td><td class="text-ink-500">{{ $r['phone'] ?: '—' }}</td><td class="text-right">{{ $r[$isR ? 'invoices' : 'orders'] }}</td>
                            <td class="text-right">{{ $r['d30'] ? amount($r['d30']) : '—' }}</td><td class="text-right">{{ $r['d60'] ? amount($r['d60']) : '—' }}</td><td class="text-right">{{ $r['d90'] ? amount($r['d90']) : '—' }}</td>
                            <td class="text-right {{ $r['older'] > 0 ? 'font-semibold text-brand-600' : '' }}">{{ $r['older'] ? amount($r['older']) : '—' }}</td><td class="text-right font-extrabold">{{ amount($r['total']) }}</td></tr>
                    @empty <tr><td colspan="8" class="py-10 text-center text-ink-400">Nothing owed. 🎉</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
            <p class="hint px-5 py-3">Ages count from the invoice / order date. Opening balances brought from the old system are shown as over 90 days.</p>
        </div>

    {{-- =============================================================== STOCK --}}
    @elseif ($report === 'stock')
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Stock value at cost</p><p class="mt-1 text-2xl font-extrabold">{{ money($data['costValue']) }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Stock value at selling price</p><p class="mt-1 text-2xl font-extrabold">{{ money($data['sellValue']) }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Parts to re-order</p><p class="mt-1 text-2xl font-extrabold {{ count($data['low']) ? 'text-amber-600' : '' }}">{{ count($data['low']) }}</p></div>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h3 class="card-title">Best-selling parts — {{ $period }}</h3></div>
            <table class="table">
                <thead><tr><th>#</th><th>Part</th><th class="text-right">Qty sold</th><th class="text-right">Revenue</th><th class="text-right">Profit</th><th class="text-right">In stock now</th></tr></thead>
                <tbody>
                @forelse ($data['top'] as $i => $p)
                    <tr><td class="text-ink-400">{{ $i + 1 }}</td><td><div class="font-medium">{{ $p['name'] }}</div><div class="font-mono text-xs text-ink-400">{{ $p['sku'] }}</div></td>
                        <td class="text-right">{{ $fmt($p['qty']) }} {{ $p['unit'] }}</td><td class="text-right font-semibold">{{ amount($p['revenue']) }}</td><td class="text-right">{{ amount($p['profit']) }}</td><td class="text-right">{{ $fmt($p['stock']) }}</td></tr>
                @empty <tr><td colspan="6" class="py-8 text-center text-ink-400">No parts sold in this period.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Re-order list</h3>
                    @if (count($data['low'])) <a href="{{ route('purchase-orders.create') }}" wire:navigate class="btn-dark btn-sm print:hidden">Create purchase order</a> @endif</div>
                <table class="table">
                    <thead><tr><th>Part</th><th class="text-right">In stock</th><th class="text-right">Re-order at</th></tr></thead>
                    <tbody>
                    @forelse ($data['low'] as $p)
                        <tr><td>{{ $p['name'] }} <span class="font-mono text-xs text-ink-400">{{ $p['sku'] }}</span></td><td class="text-right font-bold {{ $p['stock'] <= 0 ? 'text-brand-600' : 'text-amber-600' }}">{{ $fmt($p['stock']) }}</td><td class="text-right">{{ $fmt($p['reorder']) }}</td></tr>
                    @empty <tr><td colspan="3" class="py-8 text-center text-ink-400">Nothing below its re-order level.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Not moving (no sales in 90 days)</h3></div>
                <table class="table">
                    <thead><tr><th>Part</th><th class="text-right">In stock</th><th class="text-right">Value at cost</th></tr></thead>
                    <tbody>
                    @forelse ($data['dead'] as $p)
                        <tr><td>{{ $p['name'] }} <span class="font-mono text-xs text-ink-400">{{ $p['sku'] }}</span></td><td class="text-right">{{ $fmt($p['stock']) }} {{ $p['unit'] }}</td><td class="text-right font-semibold">{{ amount($p['value']) }}</td></tr>
                    @empty <tr><td colspan="3" class="py-8 text-center text-ink-400">Everything in stock has moved recently.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h3 class="card-title">Stock by category</h3></div>
            <table class="table">
                <thead><tr><th>Category</th><th class="text-right">Parts</th><th class="text-right">Units</th><th class="text-right">Value at cost</th><th class="text-right">Value at selling</th></tr></thead>
                <tbody>@foreach ($data['byCategory'] as $c)
                    <tr><td class="font-semibold">{{ $c['category'] }}</td><td class="text-right">{{ $c['items'] }}</td><td class="text-right">{{ $fmt($c['qty']) }}</td><td class="text-right">{{ amount($c['cost_value']) }}</td><td class="text-right">{{ amount($c['sell_value']) }}</td></tr>
                @endforeach</tbody>
            </table>
        </div>

    {{-- =============================================================== MECHANICS --}}
    @elseif ($report === 'mechanics')
        <div class="card overflow-hidden">
            <div class="card-header"><h3 class="card-title">Work invoiced per mechanic — {{ $period }}</h3></div>
            @php $maxV = max(1, collect($data)->max('value') ?? 1); @endphp
            <table class="table">
                <thead><tr><th>Mechanic</th><th class="text-right">Jobs invoiced</th><th class="text-right">Labour</th><th class="text-right">Job value</th><th class="w-1/4"></th><th class="text-right">Open now</th></tr></thead>
                <tbody>
                @forelse ($data as $m)
                    <tr><td class="font-semibold">{{ $m['name'] }}</td><td class="text-right">{{ $m['jobs'] }}</td><td class="text-right">{{ amount($m['labour']) }}</td><td class="text-right font-bold">{{ amount($m['value']) }}</td>
                        <td><div class="h-2 rounded-full bg-ink-100"><div class="h-2 rounded-full" style="width: {{ $m['value'] / $maxV * 100 }}%; background: #2a78d6"></div></div></td>
                        <td class="text-right">{{ $m['open'] }}</td></tr>
                @empty <tr><td colspan="6" class="py-10 text-center text-ink-400">No garage invoices in this period.</td></tr> @endforelse
                </tbody>
            </table>
        </div>

    {{-- =============================================================== SERVICES --}}
    @elseif ($report === 'services')
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Vehicles received</p><p class="mt-1 text-2xl font-extrabold">{{ $data['jobsIn'] }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Delivered</p><p class="mt-1 text-2xl font-extrabold">{{ $data['delivered'] }}</p></div>
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Average time in workshop</p><p class="mt-1 text-2xl font-extrabold">{{ $data['avgDays'] !== null ? $data['avgDays'].' days' : '—' }}</p></div>
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card overflow-hidden lg:col-span-2">
                <div class="card-header"><h3 class="card-title">Top services — {{ $period }}</h3></div>
                <table class="table">
                    <thead><tr><th>Service</th><th class="text-right">Times</th><th class="text-right">Qty / hours</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody>
                    @forelse ($data['top'] as $s)
                        <tr><td class="font-medium">{{ $s['name'] }}</td><td class="text-right">{{ $s['times'] }}</td><td class="text-right">{{ $fmt($s['qty']) }}{{ $s['hourly'] ? ' hrs' : '' }}</td><td class="text-right font-bold">{{ amount($s['revenue']) }}</td></tr>
                    @empty <tr><td colspan="4" class="py-8 text-center text-ink-400">No services invoiced in this period.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Jobs by status</h3></div>
                    <ul class="divide-y divide-ink-100 text-sm">
                        @forelse ($data['byStatus'] as $st => $n)
                            <li class="flex justify-between px-5 py-2.5"><span class="badge {{ \App\Models\JobCard::BADGES[$st] ?? 'bg-ink-100' }}">{{ $st }}</span><b>{{ $n }}</b></li>
                        @empty <li class="px-5 py-6 text-center text-ink-400">No jobs.</li> @endforelse
                    </ul>
                </div>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Vehicle makes</h3></div>
                    <ul class="divide-y divide-ink-100 text-sm">
                        @forelse ($data['makes'] as $make => $n) <li class="flex justify-between px-5 py-2.5"><span>{{ $make }}</span><b>{{ $n }}</b></li>
                        @empty <li class="px-5 py-6 text-center text-ink-400">No jobs.</li> @endforelse
                    </ul>
                </div>
            </div>
        </div>
    @endif
    </div>
</div>
