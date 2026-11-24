<div class="wrap max-w-[520px] py-14">
    <h1 class="font-display text-4xl font-extrabold tracking-tight">{{ __('One last step') }}</h1>
    <p class="mt-2 text-[#4B5563]">{{ __('Add your mobile number. We call it to confirm your orders and tell you when they are ready.') }}</p>
    @if ($email)
        <p class="mt-4 flex items-center gap-2 rounded-md bg-[#F4F5F7] px-3 py-2 text-sm text-[#4B5563]"><x-shop.google-mark class="h-4 w-4" /> {{ $email }}</p>
    @endif
    <form wire:submit="finish" class="mt-8 space-y-5">
        <div>
            <label for="name" class="s-label">{{ __('Full name') }}</label>
            <input id="name" wire:model="name" class="s-input" autocomplete="name">
            @error('name') <p class="s-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="s-label">{{ __('Mobile number') }}</label>
            <input id="phone" wire:model="phone" type="tel" inputmode="tel" class="s-input" placeholder="0754 123 456" autocomplete="tel" autofocus>
            @error('phone') <p class="s-error">{{ $message }}</p> @enderror
            @if ($taken)
                <a href="{{ route('shop.login') }}" wire:navigate class="mt-2 inline-block text-sm font-semibold underline">{{ __('Log in with your password') }}</a>
            @endif
        </div>
        <button class="s-btn-ink w-full" wire:loading.attr="disabled">{{ __('Finish signing up') }}</button>
    </form>
</div>
