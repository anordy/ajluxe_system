<div class="relative">
    <div class="grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-50">
        @foreach ($parts as $i => $p)
            <x-shop.part-card :part="$p" wire:key="cpp{{ $p->id }}" @class(['lg:max-xl:hidden' => $i >= 6]) />
        @endforeach
    </div>
    @if ($q !== '' && $parts->isEmpty() && $cats)
        <p class="rounded-xl border border-dashed border-[#16181B]/15 p-10 text-center text-sm text-[#6B7280]">{{ __('Nothing in stock in this category right now.') }}</p>
    @endif
</div>
