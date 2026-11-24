{{-- Customer form fields (shared by the Customers list and the customer profile). Expects $types. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="label">Customer / company name *</label>
        <input wire:model="form.name" class="input" autofocus>
        @error('form.name') <p class="error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="label">Phone</label>
        <input wire:model="form.phone" class="input" placeholder="07xx xxx xxx">
        @error('form.phone') <p class="error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="label">Other phone</label>
        <input wire:model="form.alt_phone" class="input">
    </div>
    <div>
        <label class="label">Type</label>
        <select wire:model="form.type" class="input">
            @foreach ($types as $t) <option>{{ $t }}</option> @endforeach
        </select>
    </div>
    <div>
        <label class="label">Email</label>
        <input type="email" wire:model="form.email" class="input">
        @error('form.email') <p class="error">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label class="label">Address</label>
        <input wire:model="form.address" class="input" placeholder="Area, city">
    </div>
    <div>
        <label class="label">TIN</label>
        <input wire:model="form.tin" class="input">
    </div>
    <div>
        <label class="label">Credit limit ({{ setting('currency') }})</label>
        <input type="number" min="0" step="any" wire:model="form.credit_limit" class="input" placeholder="0 = no credit">
        @error('form.credit_limit') <p class="error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="label">Opening balance ({{ setting('currency') }})</label>
        <input type="number" step="any" wire:model="form.opening_balance" class="input" placeholder="Amount owed before this system">
        @error('form.opening_balance') <p class="error">{{ $message }}</p> @enderror
    </div>
    <label class="flex items-center gap-2 self-end pb-2 text-sm text-ink-700">
        <input type="checkbox" wire:model="form.is_active" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Active
    </label>
    <div class="sm:col-span-2">
        <label class="label">Notes</label>
        <textarea wire:model="form.notes" rows="2" class="input"></textarea>
    </div>
</div>
