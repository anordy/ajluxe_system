@props(['title' => ''])
{{-- a simple line icon picked from the service title --}}
@php
    $t = strtolower($title);
    $d = match (true) {
        str_contains($t, 'electr')                            => 'M13 2 4 14h7l-1 8 9-12h-7l1-8Z',
        str_contains($t, 'paint') || str_contains($t, 'spray') => 'M4 4h12v5H4zM16 6.5h3v5.5h-8.5v3M9.5 15h2v6h-2z',
        str_contains($t, 'panel') || str_contains($t, 'body')  => 'M3 15v-4l2.5-5h11L20 11h1v4h-2M3 15h2m4 0h6M7 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM10 6v5h9',
        str_contains($t, 'tyre') || str_contains($t, 'wheel')  => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0-13v5m0 8v5M3 12h5m8 0h5',
        str_contains($t, 'polish') || str_contains($t, 'wash') => 'm12 3 1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3Zm6 11 .9 2.1L21 17l-2.1.9L18 20l-.9-2.1L15 17l2.1-.9L18 14Z',
        str_contains($t, 'diagnos') || str_contains($t, 'check') => 'M3 12h4l3-8 4 16 3-8h4',
        default                                                => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6Z',
    };
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes->merge(['class' => 'h-6 w-6']) }} aria-hidden="true"><path d="{{ $d }}"/></svg>
