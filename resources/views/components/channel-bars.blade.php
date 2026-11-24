@props(['values' => [], 'labels' => [], 'colors' => [], 'empty' => 'Nothing collected in this period.', 'note' => null])
{{--
    One bar per channel. Colour follows the channel (never its rank); the label and
    the figures carry the meaning, so colour is never the only cue.
    values: ['garage' => 120000, 'shop' => 40000, 'online' => 0]
--}}
@php
    $total = array_sum(array_map(fn ($v) => max(0, $v), $values));
    $max = max(1, max(array_map(fn ($v) => max(0, $v), $values ?: [0])));
@endphp
@if ($total <= 0 && collect($values)->every(fn ($v) => $v == 0))
    <p class="py-8 text-center text-sm text-ink-400">{{ $empty }}</p>
@else
    <ul class="space-y-4">
        @foreach ($values as $key => $v)
            <li title="{{ $labels[$key] ?? $key }}: {{ money($v) }}">
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="flex items-center gap-2 font-medium text-ink-800"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $colors[$key] ?? '#2a78d6' }}"></span>{{ $labels[$key] ?? $key }}</span>
                    <span class="shrink-0"><b class="tabular-nums text-ink-900 {{ $v < 0 ? 'text-brand-600' : '' }}">{{ money($v) }}</b>
                        <span class="ml-1 w-10 text-xs tabular-nums text-ink-400">{{ $total > 0 ? round(max(0, $v) / $total * 100) : 0 }}%</span></span>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-ink-100">
                    <div class="h-2 rounded-full" style="width: {{ $v > 0 ? max(2, $v / $max * 100) : 0 }}%; background: {{ $colors[$key] ?? '#2a78d6' }}"></div>
                </div>
            </li>
        @endforeach
    </ul>
    @if ($note) <p class="hint mt-4">{{ $note }}</p> @endif
@endif
