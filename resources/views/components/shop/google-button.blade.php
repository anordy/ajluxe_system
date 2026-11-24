@if (\App\Support\GoogleSignIn::enabled())
    {{-- full page load (not wire:navigate): this leaves the site for Google --}}
    <a href="{{ route('shop.google') }}" class="flex h-12 w-full items-center justify-center gap-3 rounded-md border border-[#16181B]/20 bg-white text-[15px] font-semibold text-[#16181B] transition hover:border-[#16181B] hover:bg-[#F4F5F7]">
        <x-shop.google-mark /> {{ __('Continue with Google') }}
    </a>
    <div class="flex items-center gap-3 text-xs text-[#6B7280]"><span class="h-px flex-1 bg-[#16181B]/10"></span>{{ __('or') }}<span class="h-px flex-1 bg-[#16181B]/10"></span></div>
@endif
