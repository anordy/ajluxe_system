<div>
    <h1 class="text-2xl font-extrabold text-ink-900">Welcome back</h1>
    <p class="mt-1 text-sm text-ink-500">Sign in to {{ setting('business_name') }}.</p>

    <form wire:submit="login" class="mt-8 space-y-5">
        <div>
            <label class="label" for="username">Username or email</label>
            <input id="username" type="text" wire:model="username" class="input" autocomplete="username" autofocus>
            @error('username') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div x-data="{ show: false }">
            <label class="label" for="password">Password</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" wire:model="password" class="input pr-16" autocomplete="current-password">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-ink-400 hover:text-ink-800"
                        x-text="show ? 'Hide' : 'Show'"></button>
            </div>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-600">
            <input type="checkbox" wire:model="remember" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400">
            Keep me signed in
        </label>

        <button type="submit" class="btn-primary w-full py-2.5" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>

    <p class="mt-8 text-xs text-ink-400">Forgot your password? Ask the administrator to reset it.</p>
</div>
