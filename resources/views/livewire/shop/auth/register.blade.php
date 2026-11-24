<div>
    @if (str_contains((string) session('url.intended'), '/checkout')) <x-shop.checkout-steps :step="2" /> @endif
<div class="wrap max-w-[560px] py-14">
    <h1 class="font-display text-5xl font-extrabold tracking-tight">{{ __('Create an account') }}</h1>
    <p class="mt-2 text-[#4B5563]">{{ __('Already have one?') }} <a href="{{ route('shop.login') }}" wire:navigate class="font-semibold text-brand-600 hover:underline">{{ __('Log in') }}</a></p>
    <div class="mt-8 space-y-5"><x-shop.google-button /></div>
    <form wire:submit="register" class="mt-5 space-y-5">
        <div>
            <label for="name" class="s-label">{{ __('Full name') }}</label>
            <input id="name" wire:model="name" class="s-input" autocomplete="name" autofocus>
            @error('name') <p class="s-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="s-label">{{ __('Mobile number') }}</label>
            <input id="phone" wire:model="phone" type="tel" class="s-input" autocomplete="tel" placeholder="0754 123 456">
            @error('phone') <p class="s-error">{{ $message }}</p> @enderror
            <p class="mt-1.5 text-sm text-[#6B7280]">{{ __('We use it to tell you when your order is ready. You log in with it too.') }}</p>
        </div>
        <div>
            <label for="email" class="s-label">{{ __('Email') }} <span class="font-normal text-[#6B7280]">{{ __('(optional)') }}</span></label>
            <input id="email" wire:model="email" type="email" class="s-input" autocomplete="email">
            @error('email') <p class="s-error">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="s-label">{{ __('Password') }}</label>
                <input id="password" type="password" wire:model="password" class="s-input" autocomplete="new-password">
                @error('password') <p class="s-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="s-label">{{ __('Repeat password') }}</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" class="s-input" autocomplete="new-password">
            </div>
        </div>
        <p class="text-sm text-[#6B7280]">{{ __('At least 8 characters.') }}</p>
        <button class="s-btn-red w-full" wire:loading.attr="disabled">{{ __('Create account') }}</button>
    </form>
</div>
</div>
