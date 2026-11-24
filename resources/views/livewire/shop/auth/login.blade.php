<div>
    @if (str_contains((string) session('url.intended'), '/checkout')) <x-shop.checkout-steps :step="2" /> @endif
<div class="wrap grid max-w-[1000px] gap-12 py-14 lg:grid-cols-2">
    <div>
        <h1 class="font-display text-5xl font-extrabold tracking-tight">{{ __('Log in') }}</h1>
        <p class="mt-2 text-[#4B5563]">{{ __('See your orders and check out faster.') }}</p>
        <div class="mt-8 space-y-5"><x-shop.google-button /></div>
        @if (session('google_signup'))
            <p class="mt-5 rounded-md bg-[#F4F5F7] px-3 py-2 text-sm text-[#4B5563]">{{ __('Log in with your password once and your Google account will be linked. Next time, just use Google.') }}</p>
        @endif
        <form wire:submit="signIn" class="mt-5 space-y-5">
            <div>
                <label for="login" class="s-label">{{ __('Phone number or email') }}</label>
                <input id="login" wire:model="login" class="s-input" autocomplete="username" autofocus>
                @error('login') <p class="s-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="s-label">{{ __('Password') }}</label>
                <input id="password" type="password" wire:model="password" class="s-input" autocomplete="current-password">
                @error('password') <p class="s-error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2.5 text-[15px]"><input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-[#16181B]/30 text-[#16181B] focus:ring-[#16181B]"> {{ __('Keep me logged in on this device') }}</label>
            <button class="s-btn-ink w-full" wire:loading.attr="disabled">{{ __('Log in') }}</button>
            <p class="text-sm text-[#4B5563]">{{ __('Forgot your password? Call or WhatsApp us on :phone and we will reset it.', ['phone' => setting('whatsapp') ?: setting('phone')]) }}</p>
        </form>
    </div>
    <div class="h-fit border-2 border-[#16181B] p-8">
        <h2 class="font-display text-3xl font-extrabold">{{ __('New here?') }}</h2>
        <p class="mt-2 text-[#4B5563]">{{ __('Create an account with your phone number to place orders and follow them until they are ready.') }}</p>
        <a href="{{ route('shop.register') }}" wire:navigate class="s-btn-red mt-6">{{ __('Sign up') }}</a>
    </div>
</div>
</div>
