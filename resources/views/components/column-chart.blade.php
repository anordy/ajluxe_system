@props(['data' => [], 'series' => ['garage' => 'Garage', 'counter' => 'Spare parts'], 'height' => 220, 'colors' => null])
{{--
    Stacked column chart, plain HTML + Alpine.
    data: [['label' => '01 Nov', 'garage' => 120000, 'counter' => 40000], ...]
    Colours: validated categorical pair (blue, orange); red stays reserved for "owed / overdue".
--}}
@php
    // colour follows the business, never its position: garage = blue, spare parts = orange
    $palette = ['garage' => '#2a78d6', 'counter' => '#eb6834'];
    $colors = $colors ?? array_values(array_map(fn ($k) => $palette[$k] ?? '#2a78d6', array_keys($series)));
    $keys = array_keys($series);
    $max = collect($data)->map(fn ($d) => collect($keys)->sum(fn ($k) => (float) ($d[$k] ?? 0)))->max() ?: 0;
    // clean axis maximum: 1, 2, 2.5 or 5 × 10^n
    $nice = function ($v) {
        if ($v <= 0) return 1000;
        $p = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) { if ($v <= $m * $p) return $m * $p; }
        return 10 * $p;
    };
    $top = $nice($max);
    $ticks = [0, $top / 4, $top / 2, $top * 3 / 4, $top];
    $short = fn ($n) => $n >= 1000000 ? rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.').'M' : ($n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.').'K' : number_format($n));
    $count = count($data);
    $labelEvery = max(1, (int) ceil($count / 8));
    $uid = 'cc'.substr(md5(json_encode($data)), 0, 6);
@endphp
<div x-data="{ tip: null, x: 0, table: false }" class="relative">
    {{-- legend --}}
    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-ink-600">
        @if (count($series) > 1)
            @foreach ($series as $k => $label)
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $colors[$loop->index] }}"></span>{{ $label }}</span>
            @endforeach
        @endif
        <button type="button" class="ml-auto font-semibold text-ink-400 hover:text-ink-900" @click="table = !table" x-text="table ? 'Show chart' : 'Show as table'"></button>
    </div>

    <div x-show="!table" class="flex gap-2">
        {{-- y axis --}}
        <div class="relative w-10 shrink-0 text-right text-[10px] text-ink-400" style="height: {{ $height }}px">
            @foreach ($ticks as $t)
                <span class="absolute right-0 translate-y-1/2 leading-none" style="bottom: {{ $top ? $t / $top * 100 : 0 }}%">{{ $short($t) }}</span>
            @endforeach
        </div>
        <div class="min-w-0 flex-1">
            <div class="relative border-b border-ink-200" style="height: {{ $height }}px" @mouseleave="tip = null">
                @foreach ($ticks as $t) @if ($t > 0)
                    <div class="pointer-events-none absolute inset-x-0 border-t border-ink-100" style="bottom: {{ $t / $top * 100 }}%"></div>
                @endif @endforeach
                <div class="absolute inset-0 flex items-end">
                    @foreach ($data as $i => $d)
                        @php $sum = collect($keys)->sum(fn ($k) => (float) ($d[$k] ?? 0)); @endphp
                        <div class="group relative flex h-full flex-1 cursor-default items-end justify-center" tabindex="0"
                             @mouseenter="tip = {{ $i }}; x = $el.offsetLeft + $el.offsetWidth / 2" @focus="tip = {{ $i }}; x = $el.offsetLeft + $el.offsetWidth / 2" @blur="tip = null">
                            <div class="flex w-full max-w-[24px] flex-col-reverse gap-[2px] transition group-hover:opacity-80" style="height: {{ $top ? $sum / $top * 100 : 0 }}%">
                                @foreach ($keys as $si => $k)
                                    @php $v = (float) ($d[$k] ?? 0); @endphp
                                    @if ($v > 0)
                                        <div style="flex: {{ $v }} 1 0; background: {{ $colors[$si] }}; min-height: 2px"
                                             class="{{ $loop->last || collect(array_slice($keys, $si + 1))->every(fn ($k2) => (float) ($d[$k2] ?? 0) <= 0) ? 'rounded-t' : '' }}"></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                {{-- tooltip --}}
                <template x-if="tip !== null">
                    <div class="pointer-events-none absolute -top-2 z-10 min-w-[190px] -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-ink-950 px-3 py-2 text-xs text-white shadow-lg"
                         :style="`left: ${Math.min(Math.max(x, 100), $el.parentElement.offsetWidth - 100)}px`">
                        <div class="mb-1 font-semibold" x-text="$refs.{{ $uid }}.children[tip].dataset.label"></div>
                        @foreach ($series as $k => $label)
                            <div class="flex items-center justify-between gap-4"><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" style="background: {{ $colors[$loop->index] }}"></span>{{ $label }}</span>
                                <span class="font-semibold" x-text="$refs.{{ $uid }}.children[tip].dataset.{{ $k }}"></span></div>
                        @endforeach
                        <div class="mt-1 flex justify-between border-t border-white/20 pt-1"><span>Total</span><span class="font-bold" x-text="$refs.{{ $uid }}.children[tip].dataset.total"></span></div>
                    </div>
                </template>
            </div>
            {{-- x labels --}}
            <div class="relative mt-1.5 flex h-4 text-[10px] text-ink-400">
                @foreach ($data as $i => $d)
                    <div class="relative flex-1">
                        @if ($i % $labelEvery === 0) <span class="absolute left-1/2 -translate-x-1/2 whitespace-nowrap">{{ $d['label'] }}</span> @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- values for the tooltip (text only, escaped by Blade) --}}
    <div x-ref="{{ $uid }}" class="hidden">
        @foreach ($data as $d)
            <span data-label="{{ $d['label'] }}" @foreach ($keys as $k) data-{{ $k }}="{{ money($d[$k] ?? 0) }}" @endforeach
                  data-total="{{ money(collect($keys)->sum(fn ($k) => (float) ($d[$k] ?? 0))) }}"></span>
        @endforeach
    </div>

    {{-- table view --}}
    <div x-show="table" x-cloak class="max-h-80 overflow-y-auto rounded-lg border border-ink-100">
        <table class="table">
            <thead><tr><th>Date</th>@foreach ($series as $label) <th class="text-right">{{ $label }}</th> @endforeach<th class="text-right">Total</th></tr></thead>
            <tbody>
            @foreach ($data as $d)
                <tr><td>{{ $d['label'] }}</td>@foreach ($keys as $k) <td class="text-right">{{ amount($d[$k] ?? 0) }}</td> @endforeach
                    <td class="text-right font-semibold">{{ amount(collect($keys)->sum(fn ($k) => (float) ($d[$k] ?? 0))) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
