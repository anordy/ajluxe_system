<div class="space-y-5">
    @if ($unpriced && $this->canManage())
        <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <x-icon name="alert" class="h-5 w-5 shrink-0" />
            <p><b>{{ $unpriced }} {{ Str::plural('service', $unpriced) }}</b> still {{ $unpriced === 1 ? 'has' : 'have' }} a price of 0. Click the pencil to set your price.</p>
        </div>
    @endif

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Search services…">
        </div>
        <select wire:model.live="category" class="input lg:max-w-[200px]">
            <option value="">All categories</option>
            @foreach ($categories as $c) <option>{{ $c }}</option> @endforeach
        </select>
        @if ($this->canManage())
            <button class="btn-primary lg:ml-auto" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New Service</button>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Service</th><th>Category</th><th>Charged</th><th class="text-right">Rate</th><th class="text-right">Est. hours</th><th class="text-right">Typical charge</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                @forelse ($services as $s)
                    <tr wire:key="s{{ $s->id }}" class="{{ $s->is_active ? '' : 'opacity-50' }}">
                        <td><div class="font-semibold text-ink-900">{{ $s->name }}</div><div class="font-mono text-[11px] text-ink-400">{{ $s->code }}</div></td>
                        <td>{{ $s->category ?: '—' }}</td>
                        <td>
                            @if ($s->isHourly()) <span class="badge-dark">Per hour</span> @else <span class="badge-gray">Fixed price</span> @endif
                        </td>
                        <td class="text-right font-semibold {{ (float) $s->rate ? '' : 'text-amber-600' }}">{{ $s->priceLabel() }}</td>
                        <td class="text-right">{{ (float) $s->est_hours ? rtrim(rtrim(number_format((float) $s->est_hours, 2), '0'), '.') : '—' }}</td>
                        <td class="text-right text-ink-500">{{ money($s->defaultAmount()) }}</td>
                        <td class="whitespace-nowrap text-right">
                            @if ($this->canManage())
                                <button class="btn-icon" title="Edit" wire:click="edit({{ $s->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button class="btn-icon" title="{{ $s->is_active ? 'Deactivate' : 'Activate' }}" wire:click="toggleActive({{ $s->id }})"><x-icon name="{{ $s->is_active ? 'x' : 'check' }}" class="h-4 w-4" /></button>
                                <button class="btn-icon hover:!text-brand-600" title="Delete" wire:click="delete({{ $s->id }})" wire:confirm="Delete {{ $s->name }}?"><x-icon name="trash" class="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-ink-400">No services found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-xs text-ink-400"><b>Fixed price</b>: one charge per job. <b>Per hour</b>: rate × hours worked — the job card starts with the estimated hours and you change it to the actual hours.</p>

    <x-modal show="showForm" :title="$editingId ? 'Edit Service' : 'New Service'">
        <form wire:submit="save" id="serviceForm" class="grid gap-4 sm:grid-cols-2" x-data="{ pricing: @entangle('form.pricing') }">
            <div class="sm:col-span-2">
                <label class="label">Service name *</label>
                <input wire:model="form.name" class="input">
                @error('form.name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="label">Category</label>
                <select wire:model="form.category" class="input">
                    <option value="">—</option>
                    @foreach ($categories as $c) <option>{{ $c }}</option> @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="label">How is it charged?</label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (\App\Models\Service::PRICING as $key => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-semibold"
                               :class="pricing === '{{ $key }}' ? 'border-ink-900 bg-ink-900 text-white' : 'border-ink-200 text-ink-700'">
                            <input type="radio" value="{{ $key }}" x-model="pricing" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="label" x-text="pricing === 'hourly' ? 'Rate per hour' : 'Price'"></label>
                <input type="number" min="0" step="any" wire:model="form.rate" class="input">
                @error('form.rate') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Estimated hours</label>
                <input type="number" min="0" step="0.25" wire:model="form.est_hours" class="input">
                @error('form.est_hours') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-700 sm:col-span-2">
                <input type="checkbox" wire:model="form.is_active" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Active (shown when adding items to job cards)
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="serviceForm" class="btn-primary" wire:loading.attr="disabled">Save Service</button>
        </x-slot:footer>
    </x-modal>
</div>
