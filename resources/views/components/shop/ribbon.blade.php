@props(['part', 'size' => 'sm'])
{{-- classes used: ribbon-sale ribbon-new ribbon-best-offer ribbon-sold-out ribbon-lg (listed so the CSS build keeps them) --}}
@php $r = $part->ribbonBadge(); @endphp
@if ($r)
    <span {{ $attributes->class(['ribbon', 'ribbon-'.str_replace('_', '-', $r['key']), 'ribbon-lg' => $size === 'lg']) }}>{{ $r['label'] }}</span>
@endif
