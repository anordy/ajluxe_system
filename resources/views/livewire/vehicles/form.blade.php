<div>
    <x-modal show="show" :title="$vehicleId ? 'Edit Vehicle' : 'New Vehicle'" max-width="max-w-2xl">
        <form wire:submit="save" id="vehicleForm" class="grid gap-4 sm:grid-cols-2">
            {{-- owner --}}
            <div class="sm:col-span-2">
                <label class="label">Owner (customer) *</label>
                @if ($customer_id)
                    <div class="flex items-center justify-between rounded-lg border border-ink-200 bg-ink-50 px-3 py-2">
                        <span class="text-sm font-semibold text-ink-900">{{ $customerName }}</span>
                        @unless ($lockCustomer)
                            <button type="button" class="text-xs font-semibold text-brand-600 hover:underline" wire:click="pickCustomer(null)">Change</button>
                        @endunless
                    </div>
                @else
                    <div class="relative">
                        <input wire:model.live.debounce.250ms="customerSearch" class="input" placeholder="Type at least 2 letters of the name or phone…" autocomplete="off">
                        @if (strlen(trim($customerSearch)) >= 2)
                            <div class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-ink-100 bg-white shadow-lg">
                                @forelse ($matches as $m)
                                    <button type="button" wire:key="m{{ $m->id }}" wire:click="pickCustomer({{ $m->id }})"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-ink-50">
                                        <span class="font-medium text-ink-900">{{ $m->name }}</span>
                                        <span class="text-xs text-ink-400">{{ $m->phone }} · {{ $m->code }}</span>
                                    </button>
                                @empty
                                    <p class="px-3 py-2 text-sm text-ink-400">No customer found. Add the customer first in Customers.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @endif
                @error('customer_id') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Registration number *</label>
                <input wire:model="form.reg_no" class="input font-mono uppercase" placeholder="T 123 ABC">
                @error('form.reg_no') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Make</label>
                <input wire:model="form.make" list="vehicle-makes" class="input">
                <datalist id="vehicle-makes">@foreach ($makes as $m) <option value="{{ $m }}"> @endforeach</datalist>
            </div>
            <div>
                <label class="label">Model</label>
                <input wire:model="form.model" class="input" placeholder="e.g. Land Cruiser Prado">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Year</label>
                    <input type="number" wire:model="form.year" class="input" placeholder="{{ date('Y') - 8 }}">
                    @error('form.year') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Fuel</label>
                    <select wire:model="form.fuel_type" class="input">
                        <option value="">—</option>
                        @foreach ($fuels as $f) <option>{{ $f }}</option> @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Colour</label>
                <input wire:model="form.color" class="input">
            </div>
            <div>
                <label class="label">Odometer (km)</label>
                <input type="number" min="0" wire:model="form.odometer" class="input">
                @error('form.odometer') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Chassis / VIN</label>
                <input wire:model="form.vin" class="input font-mono uppercase">
            </div>
            <div>
                <label class="label">Engine number</label>
                <input wire:model="form.engine_no" class="input font-mono uppercase">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Notes</label>
                <textarea wire:model="form.notes" rows="2" class="input"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="vehicleForm" class="btn-primary" wire:loading.attr="disabled">Save Vehicle</button>
        </x-slot:footer>
    </x-modal>
</div>
