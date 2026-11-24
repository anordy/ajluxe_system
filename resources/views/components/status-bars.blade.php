@props(['rows' => []])
{{-- Job cards per stage, each bar in its status colour (the name and count carry the meaning too). --}}
@php $max = max(1, collect($rows)->max('value') ?? 1); @endphp
@if (! collect($rows)->sum('value'))
    <p class="py-6 text-center text-sm text-ink-400">No vehicles in the workshop.</p>
@else
    <ul class="space-y-3">
        @foreach ($rows as $r)
            <li>
                <a href="{{ route('job-cards.index', ['tab' => $r['label']]) }}" wire:navigate class="group block">
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 font-medium text-ink-800 group-hover:text-ink-950"><span class="h-2.5 w-2.5 rounded-full {{ \App\Models\JobCard::DOTS[$r['label']] ?? 'bg-ink-300' }}"></span>{{ $r['label'] }}</span>
                        <b class="tabular-nums text-ink-900">{{ $r['value'] }}</b>
                    </div>
                    <div class="mt-1.5 h-2 rounded-full bg-ink-100">
                        <div class="h-2 rounded-full" style="width: {{ $r['value'] > 0 ? max(2, $r['value'] / $max * 100) : 0 }}%; background: {{ \App\Models\JobCard::HEX[$r['label']] ?? '#64748b' }}"></div>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
@endif
