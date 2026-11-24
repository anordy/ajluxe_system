@props(['w' => '', 'h' => '', 'r' => ''])
{{-- a tyre sidewall with the size written on it; the parts chosen in the finder are shown in white --}}
@php
    $part = fn ($v, $ph) => [$v !== '' ? $v : $ph, $v !== '' ? '#FFFFFF' : '#8B9099'];
    [$wt, $wc] = $part($w, '205');
    [$ht, $hc] = $part($h, '55');
    [$rt, $rc] = $part($r, '16');
@endphp
<svg viewBox="0 0 420 230" {{ $attributes->merge(['class' => 'w-full']) }} role="img" aria-label="Tyre size {{ $wt }}/{{ $ht }} R{{ $rt }}">
    <defs>
        <path id="tyre-arc" d="M 62 214 A 160 160 0 0 1 358 214"/>
        <clipPath id="tyre-clip"><rect width="420" height="222"/></clipPath>
    </defs>
    <g clip-path="url(#tyre-clip)">
        <circle cx="210" cy="250" r="205" fill="#1F2226"/>
        @for ($i = 0; $i < 46; $i++)
            @php $a = deg2rad(180 + $i * 4); @endphp
            <rect x="{{ 210 + 196 * cos($a) - 5 }}" y="{{ 250 + 196 * sin($a) - 9 }}" width="10" height="18" rx="2" fill="#2C3036"
                  transform="rotate({{ $i * 4 + 90 }} {{ 210 + 196 * cos($a) }} {{ 250 + 196 * sin($a) }})"/>
        @endfor
        <circle cx="210" cy="250" r="182" fill="#272A2F"/>
        <circle cx="210" cy="250" r="118" fill="#C9CDD2"/>
        <circle cx="210" cy="250" r="104" fill="#E5E7EB"/>
        @foreach (range(0, 324, 36) as $deg)
            <rect x="205" y="152" width="10" height="72" rx="4" fill="#B4B9C0" transform="rotate({{ $deg }} 210 250)"/>
        @endforeach
        <circle cx="210" cy="250" r="26" fill="#9CA3AF"/>
    </g>
    <text font-family="Inter Tight Variable, Arial, sans-serif" font-weight="700" font-size="34" letter-spacing="1">
        <textPath href="#tyre-arc" startOffset="50%" text-anchor="middle"><tspan fill="{{ $wc }}">{{ $wt }}</tspan><tspan fill="#8B9099"> / </tspan><tspan fill="{{ $hc }}">{{ $ht }}</tspan><tspan fill="#8B9099"> R</tspan><tspan fill="{{ $rc }}">{{ $rt }}</tspan></textPath>
    </text>
</svg>
