@props(['rows' => [], 'color' => '#2a78d6', 'empty' => 'Nothing yet.', 'valueLabel' => null])
{{--
    Ranked horizontal bars. rows: [['label' => 'Oil Filter', 'value' => 12, 'display' => '12 Pcs', 'sub' => 'TZS 180,000'], ...]
    Bars are scaled to the largest value; the text carries the numbers (never colour alone).
--}}
@php $max = max(1, collect($rows)->max('value') ?? 1); @endphp
@if (! count($rows))
    <p class="py-8 text-center text-sm text-ink-400">{{ $empty }}</p>
@else
    <ol class="space-y-3.5">
        @foreach ($rows as $i => $r)
            <li class="group" title="{{ $r['label'] }} — {{ $r['display'] ?? $r['value'] }}{{ ! empty($r['sub']) ? ' · '.$r['sub'] : '' }}">
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="min-w-0 truncate"><span class="mr-1.5 text-xs text-ink-400">{{ $i + 1 }}.</span><span class="font-medium text-ink-800">{{ $r['label'] }}</span></span>
                    <span class="shrink-0 text-right"><b class="text-ink-900">{{ $r['display'] ?? $r['value'] }}</b>
                        @if (! empty($r['sub'])) <span class="ml-1 text-xs text-ink-400">{{ $r['sub'] }}</span> @endif</span>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-ink-100">
                    <div class="h-2 rounded-full transition-all group-hover:opacity-80" style="width: {{ $r['value'] > 0 ? max(2, $r['value'] / $max * 100) : 0 }}%; background: {{ $color }}"></div>
                </div>
            </li>
        @endforeach
    </ol>
@endif
