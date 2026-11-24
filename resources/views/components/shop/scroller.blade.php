@props(['label' => 'Items'])
{{-- horizontal row with arrow buttons (arrows hide at the ends); swipe on phones --}}
<div x-data="{
        atStart: true, atEnd: false,
        update() { const t = this.$refs.track; this.atStart = t.scrollLeft < 8; this.atEnd = t.scrollLeft + t.clientWidth >= t.scrollWidth - 8; },
        go(dir) { const t = this.$refs.track; t.scrollBy({ left: dir * t.clientWidth * 0.9, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
    }" x-init="$nextTick(() => update())" @resize.window.debounce.150ms="update()" class="relative">
    <div x-ref="track" @scroll.debounce.60ms="update()" role="region" aria-label="{{ $label }}" tabindex="0"
         class="-mx-1 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth px-1 pb-2 sm:gap-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {{ $slot }}
    </div>
    <button type="button" x-show="! atStart" x-cloak @click="go(-1)" aria-label="{{ __('Scroll left') }}"
            class="absolute -left-4 top-1/2 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-[#16181B]/15 bg-white shadow-md hover:border-[#16181B] sm:flex">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m15 6-6 6 6 6"/></svg>
    </button>
    <button type="button" x-show="! atEnd" x-cloak @click="go(1)" aria-label="{{ __('Scroll right') }}"
            class="absolute -right-4 top-1/2 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-[#16181B]/15 bg-white shadow-md hover:border-[#16181B] sm:flex">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 6 6 6-6 6"/></svg>
    </button>
</div>
