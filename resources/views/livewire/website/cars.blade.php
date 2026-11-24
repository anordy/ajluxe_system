<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-ink-500">These cars appear in <b>Find parts for your car</b> on the website. Cars written on parts (“Fits”) are added automatically too.</p>
        @if ($this->canManage())
            <div class="flex shrink-0 gap-2 sm:ml-auto">
                <button class="btn-light" wire:click="importFromParts">Add cars from parts</button>
                <div x-data="{ open: false }" class="relative">
                    <button type="button" class="btn-light" @click="open = !open">Import CSV</button>
                    <form x-show="open" x-cloak @click.outside="open = false" method="POST" action="{{ route('website.cars.import') }}" enctype="multipart/form-data"
                          class="absolute right-0 z-20 mt-2 w-80 space-y-3 rounded-xl bg-white p-4 text-sm shadow-xl ring-1 ring-ink-100">
                        @csrf
                        <p class="text-ink-600">Columns: <code class="text-xs">make, model, engine_code, litres, fuel, year_from, year_to</code>. Only make and model are required. Existing cars and engines are kept.</p>
                        <input type="file" name="file" accept=".csv,.txt" required class="block w-full text-sm">
                        <button class="btn-primary w-full">Import</button>
                    </form>
                </div>
                <button class="btn-primary" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New Car</button>
            </div>
        @endif
    </div>
    <div class="relative w-full sm:max-w-sm">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
        <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Make or model…">
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Make</th><th class="w-full">Model</th><th>Engines</th><th>Years</th><th>On website</th><th></th></tr></thead>
                <tbody>
                @forelse ($cars as $c)
                    <tr wire:key="car{{ $c->id }}" class="{{ $c->is_active ? '' : 'opacity-60' }}">
                        <td class="whitespace-nowrap font-semibold text-ink-900">{{ $c->make }}</td>
                        <td>{{ $c->model }}</td>
                        <td class="whitespace-nowrap">{{ $c->engines_count ?: '—' }}</td>
                        <td class="whitespace-nowrap">{{ $c->years() }}</td>
                        <td>@if ($c->is_active) <span class="badge-green">Shown</span> @else <span class="badge-gray">Hidden</span> @endif</td>
                        <td class="whitespace-nowrap text-right">
                            @if ($this->canManage())
                                <button class="btn-icon" title="Edit" wire:click="edit({{ $c->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button class="btn-icon" title="{{ $c->is_active ? 'Hide' : 'Show' }}" wire:click="toggle({{ $c->id }})"><x-icon name="{{ $c->is_active ? 'x' : 'check' }}" class="h-4 w-4" /></button>
                                <button class="btn-icon hover:!text-brand-600" title="Remove" wire:click="delete({{ $c->id }})" wire:confirm="Remove {{ $c->make }} {{ $c->model }}?"><x-icon name="trash" class="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-12 text-center text-ink-400">No cars yet. Use “Add cars from parts” to start the list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($cars->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $cars->links() }}</div> @endif
    </div>

    <x-modal show="showForm" :title="$editingId ? 'Edit Car' : 'New Car'">
        <form wire:submit="save" id="carForm" class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Make *</label>
                    <input wire:model="form.make" list="car-makes" class="input" placeholder="Toyota">
                    <datalist id="car-makes">@foreach ($makes as $m)<option value="{{ $m }}">@endforeach</datalist>
                    @error('form.make') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Model *</label>
                    <input wire:model="form.model" class="input" placeholder="Land Cruiser Prado">
                    @error('form.model') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">First year</label><input wire:model="form.year_from" inputmode="numeric" class="input" placeholder="2009">@error('form.year_from') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">Last year</label><input wire:model="form.year_to" inputmode="numeric" class="input" placeholder="Still made: leave empty">@error('form.year_to') <p class="error">{{ $message }}</p> @enderror</div>
            </div>
            <div>
                <label class="label">Engines <span class="font-normal normal-case text-ink-400">— one per line: size and fuel | engine code | years</span></label>
                <textarea wire:model="form.engines" rows="5" class="input font-mono text-sm" placeholder="3.0 Diesel | 1KD-FTV | 2004-2015&#10;2.7 Petrol | 2TR-FE"></textarea>
                <p class="hint">Engines are for staff only (linking parts, imports). The website shows make and model. Removing a line also removes the parts linked to that engine.</p>
                @error('form.engines') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" wire:model="form.is_active" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Show on the website
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="carForm" class="btn-primary" wire:loading.attr="disabled">Save Car</button>
        </x-slot:footer>
    </x-modal>
</div>
