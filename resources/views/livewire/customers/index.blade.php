<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Search name, code, phone, TIN…">
        </div>
        <select wire:model.live="type" class="input lg:max-w-[170px]">
            <option value="">All types</option>
            @foreach ($types as $t) <option>{{ $t }}</option> @endforeach
        </select>
        <select wire:model.live="status" class="input lg:max-w-[150px]">
            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option>
        </select>
        @if ($this->canManage())
            <button class="btn-primary lg:ml-auto" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New Customer</button>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Customer</th><th>Phone</th><th>Type</th><th class="text-center">Vehicles</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                @forelse ($customers as $c)
                    <tr wire:key="c{{ $c->id }}" class="{{ $c->is_active ? '' : 'opacity-60' }}">
                        <td>
                            <a href="{{ route('customers.show', $c) }}" wire:navigate class="font-semibold text-ink-900 hover:text-brand-600">{{ $c->name }}</a>
                            <div class="font-mono text-[11px] text-ink-400">{{ $c->code }}</div>
                        </td>
                        <td>{{ $c->phone ?: '—' }}</td>
                        <td><span class="badge-gray">{{ $c->type }}</span></td>
                        <td class="text-center font-semibold">{{ $c->vehicles_count }}</td>
                        <td>@if ($c->is_active) <span class="badge-green">Active</span> @else <span class="badge-red">Inactive</span> @endif</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('customers.show', $c) }}" wire:navigate class="btn-light btn-sm">Open</a>
                            @if ($this->canManage())
                                <button class="btn-icon" title="Edit" wire:click="edit({{ $c->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button class="btn-icon" title="{{ $c->is_active ? 'Deactivate' : 'Activate' }}" wire:click="toggleActive({{ $c->id }})"><x-icon name="{{ $c->is_active ? 'x' : 'check' }}" class="h-4 w-4" /></button>
                                <button class="btn-icon hover:!text-brand-600" title="Delete" wire:click="delete({{ $c->id }})"
                                        wire:confirm="Delete {{ $c->name }}? This only works for customers with no vehicles or history."><x-icon name="trash" class="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-12 text-center text-ink-400">
                        No customers found. @if ($this->canManage() && ! $search) <button wire:click="create" class="font-semibold text-brand-600 hover:underline">Add the first customer</button> @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $customers->links() }}</div> @endif
    </div>

    <x-modal show="showForm" :title="$editingId ? 'Edit Customer' : 'New Customer'" max-width="max-w-2xl">
        <form wire:submit="save" id="customerForm">@include('livewire.customers._form')</form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="customerForm" class="btn-primary" wire:loading.attr="disabled">Save Customer</button>
        </x-slot:footer>
    </x-modal>
</div>
