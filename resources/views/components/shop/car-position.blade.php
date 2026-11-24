@props(['value' => ''])
{{-- top view of a car (front at the top); the red marks show where the part fits --}}
@php
    $v = strtolower(trim((string) $value));
    $on = match (true) {
        str_contains($v, 'front') && str_contains($v, 'left')  => ['FL'],
        str_contains($v, 'front') && str_contains($v, 'right') => ['FR'],
        str_contains($v, 'rear') && str_contains($v, 'left')   => ['RL'],
        str_contains($v, 'rear') && str_contains($v, 'right')  => ['RR'],
        str_contains($v, 'front')                              => ['FL', 'FR'],
        str_contains($v, 'rear')                               => ['RL', 'RR'],
        $v === 'left'                                          => ['FL', 'RL'],
        $v === 'right'                                         => ['FR', 'RR'],
        default                                                => [],
    };
    $wheels = ['FL' => [6, 22], 'FR' => [58, 22], 'RL' => [6, 74], 'RR' => [58, 74]];
@endphp
<svg viewBox="0 0 72 112" {{ $attributes->merge(['class' => 'h-14 w-auto']) }} aria-hidden="true">
    @foreach ($wheels as $k => [$x, $y])
        <rect x="{{ $x }}" y="{{ $y }}" width="8" height="18" rx="3" fill="{{ in_array($k, $on, true) ? '#E31B23' : '#9CA3AF' }}"/>
    @endforeach
    <path d="M18 10 Q36 2 54 10 L58 30 L58 92 Q58 104 48 106 L24 106 Q14 104 14 92 L14 30 Z" fill="#fff" stroke="#16181B" stroke-width="2.5"/>
    <path d="M21 32 Q36 26 51 32 L48 46 L24 46 Z" fill="#CBD5E1"/>
    <path d="M24 84 L48 84 L50 94 Q36 98 22 94 Z" fill="#CBD5E1"/>
    <rect x="22" y="50" width="28" height="30" rx="4" fill="none" stroke="#CBD5E1" stroke-width="2"/>
    @foreach ($wheels as $k => [$x, $y])
        @if (in_array($k, $on, true))
            <circle cx="{{ $x < 30 ? 20 : 52 }}" cy="{{ $y < 50 ? 14 : 100 }}" r="4.5" fill="#E31B23"/>
        @endif
    @endforeach
</svg>
