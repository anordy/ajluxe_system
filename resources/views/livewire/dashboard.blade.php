@php
    // change against the same number of days just before
    $delta = function ($now, $prev) {
        if ($prev == 0) return $now > 0 ? ['new', 'text-emerald-600'] : null;
        $p = round(($now - $prev) / abs($prev) * 100);
        return [($p > 0 ? '+' : '').$p.'%', $p >= 0 ? 'text-emerald-600' : 'text-brand-600'];
    };
    $period = \App\Livewire\Dashboard::RANGES[$range] ?? 'This month';
@endphp
<div class="space-y-6">
    {{-- title + period --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-ink-900">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ strtok($user->name, ' ') }}</h2>
            <p class="mt-0.5 text-sm text-ink-500">
                @if ($isBoss) {{ $period }} · {{ \Carbon\Carbon::parse($from)->format('d M') }}@if ($from !== $to) – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}@else {{ \Carbon\Carbon::parse($to)->format('Y') }}@endif. Profit is counted when the customer pays.
                @else Here is how things stand at {{ setting('business_name') }}. @endif
            </p>
        </div>
        @if ($isBoss)
            <div class="flex flex-wrap items-center gap-3">
            <div class="flex gap-1 rounded-xl bg-white p-1 shadow-sm ring-1 ring-ink-100" role="group" aria-label="Business">
                @foreach (\App\Services\Analytics::BUSINESSES as $key => $label)
                    @php $on = $business === $key; $dot = ['garage' => '#2a78d6', 'spares' => '#eb6834'][$key] ?? null; @endphp
                    <button type="button" wire:click="$set('business', '{{ $key }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $on ? 'bg-ink-900 text-white' : 'text-ink-600 hover:bg-ink-50 hover:text-ink-900' }}">
                        @if ($dot) <span class="h-2 w-2 rounded-full" style="background: {{ $dot }}"></span> @endif {{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-1 rounded-xl bg-white p-1 shadow-sm ring-1 ring-ink-100" role="group" aria-label="Period">
                @foreach (\App\Livewire\Dashboard::RANGES as $key => $label)
                    <button type="button" wire:click="$set('range', '{{ $key }}')" aria-pressed="{{ $range === $key ? 'true' : 'false' }}"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $range === $key ? 'bg-ink-900 text-white' : 'text-ink-500 hover:bg-ink-50 hover:text-ink-900' }}">{{ $label }}</button>
                @endforeach
            </div>
            </div>
        @endif
    </div>

    @if ($data)
        @php $n = $data['now']; $p = $data['prev']; @endphp
        <div wire:loading.class="opacity-60" class="space-y-6 transition">
            {{-- headline figures --}}
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @php
                    $tiles = match ($business) {
                        'garage' => [
                            ['Garage money collected', money($n['money']), $n['count'].' '.($n['count'] === 1 ? 'payment' : 'payments'), $delta($n['money'], $p['money'])],
                            ['Garage profit collected', money($n['profit']), $n['margin'].'% · parts at the shop\'s price', $delta($n['profit'], $p['profit'])],
                            ['Labour collected', money($n['labourSales']), 'Parts fitted: '.money($n['garagePartsSales']), $delta($n['labourSales'], $p['labourSales'])],
                            ['Garage still to collect', money($n['owedTotal']), 'Includes '.money($n['owedProfit']).' profit', null],
                        ],
                        'spares' => [
                            ['Spare parts sales collected', money($n['money']), 'Shop, online and parts supplied to the garage', $delta($n['money'], $p['money'])],
                            ['Spare parts profit collected', money($n['profit']), $n['margin'].'% · selling price − buying price', $delta($n['profit'], $p['profit'])],
                            ['Stock value (at cost)', money($data['stockValue']), $counts['low'].' low · '.$counts['out'].' out of stock', null],
                            ['Shop & online still to collect', money($n['owedTotal']), 'Includes '.money($n['owedProfit']).' profit', null],
                        ],
                        default => [
                            ['Money collected', money($n['money']), $n['count'].' '.($n['count'] === 1 ? 'payment' : 'payments'), $delta($n['money'], $p['money'])],
                            ['Profit collected', money($n['profit']), $n['margin'].'% of money collected', $delta($n['profit'], $p['profit'])],
                            ['Spare parts sold', money($n['partsSales']), 'Paid for, in all channels', $delta($n['partsSales'], $p['partsSales'])],
                            ['Still to collect', money($n['owedTotal']), 'Includes '.money($n['owedProfit']).' profit', null],
                        ],
                    };
                @endphp
                @foreach ($tiles as [$label, $value, $sub, $d])
                    <div class="card p-5">
                        <p class="text-sm font-medium text-ink-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-extrabold tabular-nums tracking-tight text-ink-900">{{ $value }}</p>
                        <p class="mt-1 flex flex-wrap gap-x-2 text-xs text-ink-400">
                            <span>{{ $sub }}</span>
                            @if ($d) <span><b class="{{ $d[1] }}">{{ $d[0] }}</b> vs previous {{ $range === 'today' ? 'day' : 'period' }}</span> @endif
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- money in over time + profit by channel --}}
            @php
                $trendTitle = ['garage' => 'Garage money collected', 'spares' => 'Spare parts sales by channel'][$business] ?? 'Money collected by channel';
                $trendSub = ['garage' => 'Payments for garage jobs', 'spares' => 'Shop counter, website orders and parts fitted on garage jobs (once the garage is paid)'][$business] ?? 'Garage jobs, shop counter and website orders';
                $mix = [
                    ['label' => 'Labour', 'value' => max(0, $n['labourSales']), 'display' => money($n['labourSales']), 'sub' => money($n['labourProfit']).' profit'],
                    ['label' => 'Parts fitted', 'value' => max(0, $n['garagePartsSales']), 'display' => money($n['garagePartsSales']), 'sub' => money($n['garagePartsProfit']).' profit'],
                ];
            @endphp
            <div class="grid gap-6 xl:grid-cols-5">
                <div class="card p-5 xl:col-span-3">
                    <h3 class="card-title mb-1">{{ $trendTitle }}</h3>
                    <p class="mb-4 text-xs text-ink-400">{{ $range === 'today' ? 'Last 14 days' : $period }} · {{ $trendSub }}</p>
                    <x-column-chart :data="$data['trend']" :series="$channels" :colors="array_values($colors)" :height="210" />
                </div>
                <div class="card p-5 xl:col-span-2">
                    @if ($business === 'garage')
                        <h3 class="card-title mb-1">Labour and parts</h3>
                        <p class="mb-4 text-xs text-ink-400">Collected on garage jobs · parts profit is the garage's margin over the shop's price</p>
                        @if ($n['labourSales'] + $n['garagePartsSales'] > 0)
                            <x-bar-list :rows="$mix" :color="$colors['garage']" />
                        @else
                            <p class="py-8 text-center text-sm text-ink-400">No garage payments in this period.</p>
                        @endif
                    @else
                        <h3 class="card-title mb-1">{{ $business === 'spares' ? 'Spare parts profit by channel' : 'Profit collected by channel' }}</h3>
                        <p class="mb-4 text-xs text-ink-400">Each payment brings in its share of the invoice's profit</p>
                        <x-channel-bars :values="$n['profitBy']" :labels="$channels" :colors="$colors"
                            :note="$business === 'spares' ? 'Supplied to garage: the shop\'s margin on parts fitted on garage jobs, counted when the garage customer pays.' : 'Parts used on garage jobs are costed at the buying price, so their profit shows under Garage.'" />
                    @endif
                </div>
            </div>

            @if ($business === 'all')
            {{-- what drives spare-parts sales, garage mix, how people pay --}}
            <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
                <div class="card p-5">
                    <h3 class="card-title mb-1">Where spare parts are sold</h3>
                    <p class="mb-4 text-xs text-ink-400">Parts paid for, by the channel that sold them</p>
                    <x-channel-bars :values="$n['partsBy']" :labels="$channels" :colors="$colors" empty="No spare parts paid for in this period." />
                    @if ($n['partsSales'] > 0)
                        <p class="hint mt-4">Profit on these parts: <b class="text-ink-700">{{ money(array_sum($n['partsProfitBy'])) }}</b></p>
                    @endif
                </div>
                <div class="card p-5">
                    <h3 class="card-title mb-1">Garage: labour and parts</h3>
                    <p class="mb-4 text-xs text-ink-400">Collected on garage jobs</p>
                    @if ($n['labourSales'] + $n['garagePartsSales'] > 0)
                        <x-bar-list :rows="$mix" :color="$colors['garage']" />
                    @else
                        <p class="py-8 text-center text-sm text-ink-400">No garage payments in this period.</p>
                    @endif
                </div>
                <div class="card p-5 lg:col-span-2 xl:col-span-1">
                    <h3 class="card-title mb-1">How customers paid</h3>
                    <p class="mb-4 text-xs text-ink-400">Money collected by payment method</p>
                    <x-bar-list :rows="array_map(fn ($m) => ['label' => $m['mode'], 'value' => max(0, $m['amount']), 'display' => money($m['amount']), 'sub' => '× '.$m['count']], $n['modes'])"
                        color="#52514e" empty="No payments in this period." />
                </div>
            </div>

            @endif

            @if ($business !== 'garage')
                {{-- spare parts: from our own shelf vs bought from suppliers' shops when sold --}}
                @php
                    $sup = $n['supply'];
                    $pct = fn ($r) => $r['sales'] > 0 ? ' · '.round($r['profit'] / $r['sales'] * 100).'%' : '';
                    $supTotal = $sup['own']['sales'] + $sup['suppliers']['sales'];
                    $supRows = [
                        ['label' => 'From our stock', 'value' => max(0, $sup['own']['sales']), 'display' => money($sup['own']['sales']), 'sub' => money($sup['own']['profit']).' profit'.$pct($sup['own'])],
                        ['label' => 'From suppliers', 'value' => max(0, $sup['suppliers']['sales']), 'display' => money($sup['suppliers']['sales']), 'sub' => money($sup['suppliers']['profit']).' profit'.$pct($sup['suppliers'])],
                    ];
                    $shopRows = array_map(fn ($r) => ['label' => $r['name'], 'value' => max(0, $r['sales']), 'display' => money($r['sales']), 'sub' => money($r['profit']).' profit'.$pct($r)], $sup['bySupplier']);
                @endphp
                <div class="card p-5">
                    <h3 class="card-title mb-1">Our stock vs suppliers</h3>
                    <p class="mb-4 text-xs text-ink-400">Spare parts paid for in the shop and online · "suppliers" are parts bought from a supplier's shop only when they sold</p>
                    @if ($supTotal != 0)
                        <div class="grid gap-6 lg:grid-cols-2">
                            <x-bar-list :rows="$supRows" color="#eb6834" />
                            <div>
                                <p class="mb-2 text-xs font-semibold text-ink-500">By supplier</p>
                                <x-bar-list :rows="$shopRows" color="#52514e" empty="Nothing bought from suppliers in this period." />
                            </div>
                        </div>
                    @else
                        <p class="py-6 text-center text-sm text-ink-400">No spare parts paid for in this period.</p>
                    @endif
                </div>
            @endif

            {{-- best sellers --}}
            <div class="grid gap-6 {{ $business === 'all' ? 'lg:grid-cols-2' : 'lg:grid-cols-2 xl:grid-cols-3' }}">
                @if ($business !== 'garage')
                    <div class="card p-5 {{ $business === 'spares' ? 'xl:col-span-2' : '' }}">
                        <h3 class="card-title mb-1">Top-selling spare parts</h3>
                        <p class="mb-4 text-xs text-ink-400">Invoiced in this period · shop, online and garage jobs</p>
                        <x-bar-list :rows="array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['revenue'], 'display' => money($r['revenue']), 'sub' => rtrim(rtrim(number_format($r['qty'], 2), '0'), '.').' '.$r['unit']], $data['topParts'])"
                            color="#eb6834" empty="No parts sold in this period." />
                    </div>
                @endif
                @if ($business !== 'spares')
                    <div class="card p-5">
                        <h3 class="card-title mb-1">Most common garage jobs</h3>
                        <p class="mb-4 text-xs text-ink-400">Invoiced in this period</p>
                        <x-bar-list :rows="array_map(fn ($s) => ['label' => $s['name'], 'value' => $s['times'], 'display' => $s['times'].' '.($s['times'] === 1 ? 'job' : 'jobs'), 'sub' => money($s['revenue'])], $data['topServices'])"
                            color="#2a78d6" empty="No garage jobs invoiced in this period." />
                    </div>
                @endif
                @if ($business === 'garage')
                    <div class="card p-5">
                        <h3 class="card-title mb-1">Parts fitted on garage jobs</h3>
                        <p class="mb-4 text-xs text-ink-400">Invoiced in this period</p>
                        <x-bar-list :rows="array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['revenue'], 'display' => money($r['revenue']), 'sub' => rtrim(rtrim(number_format($r['qty'], 2), '0'), '.').' '.$r['unit']], $data['topParts'])"
                            color="#2a78d6" empty="No parts fitted in this period." />
                    </div>
                @endif
                @if ($business !== 'all')
                <div class="card p-5">
                    <h3 class="card-title mb-1">How customers paid</h3>
                    <p class="mb-4 text-xs text-ink-400">{{ ['garage' => 'Garage payments', 'spares' => 'Shop and online payments'][$business] ?? 'Money collected' }} by payment method</p>
                    <x-bar-list :rows="array_map(fn ($m) => ['label' => $m['mode'], 'value' => max(0, $m['amount']), 'display' => money($m['amount']), 'sub' => '× '.$m['count']], $n['modes'])"
                        color="#52514e" empty="No payments in this period." />
                </div>
                @endif
            </div>

            {{-- still to collect + the business right now --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card p-5">
                    <h3 class="card-title mb-1">{{ ['garage' => 'Garage customers still owe', 'spares' => 'Shop & online customers still owe'][$business] ?? 'Customers still owe' }}</h3>
                    <p class="mb-4 text-xs text-ink-400">All unpaid invoices · this profit is counted when it is paid</p>
                    <x-channel-bars :values="$n['owed']" :labels="$channels" :colors="$colors" empty="Every invoice is paid." />
                </div>
                <div class="card p-5">
                    <h3 class="card-title mb-1">Right now</h3>
                    <p class="mb-4 text-xs text-ink-400">{{ ['garage' => 'Workshop today', 'spares' => 'Stock and orders today'][$business] ?? 'Workshop and stock today' }}</p>
                    @php
                        $now = [];
                        if ($business !== 'spares') $now[] = ['Vehicles in workshop', number_format($counts['workshop']), route('job-cards.index'), ''];
                        if ($business !== 'garage') {
                            $now[] = ['New online orders', number_format($counts['newOrders']), route('orders.index'), $counts['newOrders'] ? 'text-brand-600' : ''];
                            $now[] = ['Stock value (cost)', money($data['stockValue']), route('stock.index'), ''];
                            $now[] = ['Low stock', number_format($counts['low']), route('stock.index'), $counts['low'] ? 'text-amber-600' : ''];
                            $now[] = ['Out of stock', number_format($counts['out']), route('stock.index'), $counts['out'] ? 'text-brand-600' : ''];
                            $now[] = ['Owed to suppliers', money($data['payable']), route('purchase-orders.index'), ''];
                        }
                    @endphp
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-5 text-sm sm:grid-cols-3">
                        @foreach ($now as [$label, $value, $url, $tone])
                            <a href="{{ $url }}" wire:navigate class="group">
                                <dt class="text-xs text-ink-400 group-hover:text-ink-700">{{ $label }}</dt>
                                <dd class="mt-0.5 text-lg font-bold tabular-nums {{ $tone ?: 'text-ink-900' }}">{{ $value }}</dd>
                            </a>
                        @endforeach
                    </dl>
                    @if ($business !== 'spares')
                        <div class="mt-6">
                            <p class="mb-3 text-xs font-medium text-ink-500">Open job cards by stage</p>
                            <x-status-bars :rows="$workshop" />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        {{-- staff and mechanics: figures without money --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($user->hasRole('staff'))
                @foreach ([['Shop sales today', money($counts['shopToday']), $counts['salesToday'].' '.($counts['salesToday'] === 1 ? 'sale' : 'sales')], ['New online orders', number_format($counts['newOrders']), 'waiting to be confirmed'], ['Low stock', number_format($counts['low']), 'at or below re-order level'], ['Out of stock', number_format($counts['out']), 'active parts']] as [$label, $value, $sub])
                    <div class="card p-5"><p class="text-sm font-medium text-ink-500">{{ $label }}</p><p class="mt-1 text-2xl font-extrabold tabular-nums text-ink-900">{{ $value }}</p><p class="mt-1 text-xs text-ink-400">{{ $sub }}</p></div>
                @endforeach
            @endif
            @if ($user->hasRole('mechanic'))
                <div class="card p-5"><p class="text-sm font-medium text-ink-500">My open jobs</p><p class="mt-1 text-2xl font-extrabold tabular-nums text-ink-900">{{ $counts['workshop'] }}</p><p class="mt-1 text-xs text-ink-400">not yet delivered</p></div>
            @endif
        </div>
        @if ($user->hasRole('mechanic'))
            <div class="card p-5 lg:max-w-2xl">
                <h3 class="card-title mb-4">Open job cards by stage</h3>
                <x-status-bars :rows="$workshop" />
            </div>
        @endif
    @endif
</div>
