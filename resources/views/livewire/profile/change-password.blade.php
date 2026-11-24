<div class="mx-auto max-w-lg">
    @if (auth()->user()->must_change_password)
        <div class="mb-5 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <x-icon name="alert" class="h-5 w-5 shrink-0" />
            <p>You are using a temporary password. Please choose your own password before continuing.</p>
        </div>
    @endif

    <form wire:submit="save" class="card">
        <div class="card-header"><h2 class="card-title">Change Password</h2></div>
        <div class="card-body space-y-4">
            <div>
                <label class="label">Current password</label>
                <input type="password" wire:model="current_password" class="input" autocomplete="current-password">
                @error('current_password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">New password</label>
                <input type="password" wire:model="password" class="input" autocomplete="new-password">
                <p class="hint">At least 8 characters, with letters and numbers.</p>
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Confirm new password</label>
                <input type="password" wire:model="password_confirmation" class="input" autocomplete="new-password">
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-ink-100 px-5 py-4">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save Password</button>
        </div>
    </form>
</div>
