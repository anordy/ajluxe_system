<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Search name, code, contact, phone…">
        </div>
        <select wire:model.live="status" class="input lg:max-w-[150px]">
            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option>
        </select>
        @if ($this->canManage())
            <button class="btn-primary lg:ml-auto" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New Supplier</button>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>TIN</th><th class="text-right">We owe</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                @forelse ($suppliers as $s)
                    @php $owed = $s->balance(); @endphp
                    <tr wire:key="s{{ $s->id }}" class="{{ $s->is_active ? '' : 'opacity-60' }}">
                        <td><div class="font-semibold text-ink-900">{{ $s->name }}</div><div class="font-mono text-[11px] text-ink-400">{{ $s->code }}</div></td>
                        <td>{{ $s->contact_person ?: '—' }}</td>
                        <td>{{ $s->phone ?: '—' }}</td>
                        <td class="font-mono text-xs">{{ $s->tin ?: '—' }}</td>
                        <td class="text-right font-semibold {{ $owed > 0 ? 'text-brand-600' : '' }}">{{ money($owed) }}</td>
                        <td>@if ($s->is_active) <span class="badge-green">Active</span> @else <span class="badge-red">Inactive</span> @endif</td>
                        <td class="whitespace-nowrap text-right">
                            @if ($this->canManage())
                                <button class="btn-icon" title="Edit" wire:click="edit({{ $s->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button class="btn-icon" title="{{ $s->is_active ? 'Deactivate' : 'Activate' }}" wire:click="toggleActive({{ $s->id }})"><x-icon name="{{ $s->is_active ? 'x' : 'check' }}" class="h-4 w-4" /></button>
                                <button class="btn-icon hover:!text-brand-600" title="Delete" wire:click="delete({{ $s->id }})" wire:confirm="Delete {{ $s->name }}?"><x-icon name="trash" class="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-ink-400">No suppliers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($suppliers->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $suppliers->links() }}</div> @endif
    </div>

    <x-modal show="showForm" :title="$editingId ? 'Edit Supplier' : 'New Supplier'" max-width="max-w-2xl">
        <form wire:submit="save" id="supplierForm" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label">Supplier name *</label>
                <input wire:model="form.name" class="input">
                @error('form.name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div><label class="label">Contact person</label><input wire:model="form.contact_person" class="input"></div>
            <div><label class="label">Phone</label><input wire:model="form.phone" class="input"></div>
            <div>
                <label class="label">Email</label><input type="email" wire:model="form.email" class="input">
                @error('form.email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div><label class="label">TIN</label><input wire:model="form.tin" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Address</label><input wire:model="form.address" class="input"></div>
            <div>
                <label class="label">Opening balance ({{ setting('currency') }})</label>
                <input type="number" step="any" wire:model="form.opening_balance" class="input" placeholder="What you owe them today">
                @error('form.opening_balance') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-ink-700">
                <input type="checkbox" wire:model="form.is_active" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Active
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="supplierForm" class="btn-primary" wire:loading.attr="disabled">Save Supplier</button>
        </x-slot:footer>
    </x-modal>
</div>
