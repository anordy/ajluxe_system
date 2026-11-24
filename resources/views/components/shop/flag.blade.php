@props(['code'])
{{-- Small flag for the language picker: en = United States, sw = Tanzania. Decorative; the name sits beside it. --}}
@if ($code === 'sw')
    <svg {{ $attributes->merge(['class' => 'h-3.5 w-5 shrink-0 rounded-[2px] ring-1 ring-black/10']) }} viewBox="0 0 30 20" aria-hidden="true">
        <defs><clipPath id="tz-c"><rect width="30" height="20"/></clipPath></defs>
        <g clip-path="url(#tz-c)">
            <path d="M0 0h30L0 20z" fill="#1EB53A"/>
            <path d="M30 0v20H0z" fill="#00A3DD"/>
            <path d="M0 20 30 0" stroke="#FCD116" stroke-width="9"/>
            <path d="M0 20 30 0" stroke="#000" stroke-width="5.4"/>
        </g>
    </svg>
@else
    <svg {{ $attributes->merge(['class' => 'h-3.5 w-5 shrink-0 rounded-[2px] ring-1 ring-black/10']) }} viewBox="0 0 38 20" preserveAspectRatio="none" aria-hidden="true">
        <rect width="38" height="20" fill="#fff"/>
        @for ($i = 0; $i < 13; $i += 2) <rect y="{{ $i * 20 / 13 }}" width="38" height="{{ 20 / 13 }}" fill="#B22234"/> @endfor
        <rect width="15.2" height="{{ 7 * 20 / 13 }}" fill="#3C3B6E"/>
        @for ($r = 0; $r < 4; $r++) @for ($c = 0; $c < 5; $c++)
            <circle cx="{{ 1.6 + $c * 3 + ($r % 2) * 1.5 }}" cy="{{ 1.5 + $r * 2.6 }}" r=".55" fill="#fff"/>
        @endfor @endfor
    </svg>
@endif
