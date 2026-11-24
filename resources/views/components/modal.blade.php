@props(['show', 'title' => '', 'maxWidth' => 'max-w-lg'])
{{-- Usage: <x-modal show="showForm" title="New User"> ... <x-slot:footer>...</x-slot:footer> </x-modal> --}}
@teleport('body')
<div x-data="{ open: @entangle($show) }" x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
     @keydown.escape.window="open = false">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-ink-950/50" @click="open = false"></div>
    <div x-show="open" x-transition class="relative w-full {{ $maxWidth }} overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
        <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
            <h3 class="text-base font-bold text-ink-900">{{ $title }}</h3>
            <button type="button" class="btn-icon" @click="open = false"><x-icon name="x" class="h-4 w-4" /></button>
        </div>
        <div class="max-h-[70vh] overflow-y-auto p-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-2 border-t border-ink-100 bg-ink-50 px-5 py-3.5">{{ $footer }}</div>
        @endisset
    </div>
</div>
@endteleport
