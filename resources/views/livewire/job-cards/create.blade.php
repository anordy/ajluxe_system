<form wire:submit="save" class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('job-cards.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-ink-500 hover:text-ink-900">← Job cards</a>

    {{-- 1. customer & vehicle --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title"><span class="mr-2 text-brand-500">1</span> Customer &amp; Vehicle</h2></div>
        <div class="card-body space-y-5">
            <div>
                <label class="label">Customer or plate number *</label>
                @if ($customer)
                    <div class="flex items-center justify-between rounded-lg border border-ink-200 bg-ink-50 px-4 py-3">
                        <div><p class="font-semibold text-ink-900">{{ $customer->name }}</p><p class="text-xs text-ink-500">{{ $customer->phone }} · {{ $customer->code }}</p></div>
                        <button type="button" class="text-xs font-semibold text-brand-600 hover:underline" wire:click="pickCustomer(null)">Change</button>
                    </div>
                @else
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
                        <input wire:model.live.debounce.250ms="customerSearch" class="input pl-9" placeholder="Type a plate number, customer name or phone…" autocomplete="off" autofocus>
                        @if (strlen(trim($customerSearch)) >= 2)
                            <div class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-ink-100 bg-white shadow-lg">
                                @foreach ($plates as $v)
                                    <button type="button" wire:key="pl{{ $v->id }}" wire:click="pickPlate({{ $v->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-ink-50">
                                        <span class="rounded-md border-2 border-ink-900 bg-amber-300 px-2 py-0.5 font-mono text-xs font-bold tracking-wider">{{ $v->reg_no }}</span>
                                        <span class="text-ink-700">{{ trim($v->make.' '.$v->model) }}</span>
                                        <span class="ml-auto text-xs text-ink-400">{{ $v->customer?->name }} · {{ $v->customer?->phone }}</span>
                                    </button>
                                @endforeach
                                @if ($plates->isNotEmpty() && $matches->isNotEmpty()) <div class="border-t border-ink-100"></div> @endif
                                @forelse ($matches as $m)
                                    <button type="button" wire:key="m{{ $m->id }}" wire:click="pickCustomer({{ $m->id }})" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-ink-50">
                                        <span class="font-medium text-ink-900">{{ $m->name }}</span>
                                        <span class="text-xs text-ink-400">{{ $m->phone }} · {{ $m->vehicles_count }} {{ Str::plural('vehicle', $m->vehicles_count) }}</span>
                                    </button>
                                @empty
                                    @if ($plates->isEmpty())
                                    <p class="px-4 py-3 text-sm text-ink-500">No customer or plate found. <a href="{{ route('customers.index') }}" wire:navigate class="font-semibold text-brand-600">Add the customer first</a>.</p>
                                    @endif
                                @endforelse
                            </div>
                        @endif
                    </div>
                @endif
                @error('customer_id') <p class="error">{{ $message }}</p> @enderror
            </div>

            @if ($customer)
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="label !mb-0">Vehicle *</label>
                        <button type="button" class="text-xs font-semibold text-brand-600 hover:underline" wire:click="$dispatch('open-vehicle-form', { customerId: {{ $customer->id }} })">+ Add a vehicle</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @forelse ($vehicles as $v)
                            <button type="button" wire:key="v{{ $v->id }}" wire:click="pickVehicle({{ $v->id }})"
                                    class="flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition {{ $vehicle_id === $v->id ? 'border-ink-900 bg-ink-50' : 'border-ink-100 hover:border-ink-300' }}">
                                <span class="rounded-md border-2 border-ink-900 bg-amber-300 px-2 py-0.5 font-mono text-xs font-bold tracking-wider">{{ $v->reg_no }}</span>
                                <span class="text-sm font-medium text-ink-700">{{ trim($v->make.' '.$v->model) }} {{ $v->year ? '· '.$v->year : '' }}</span>
                                @if ($vehicle_id === $v->id) <x-icon name="check" class="ml-auto h-5 w-5 text-ink-900" /> @endif
                            </button>
                        @empty
                            <p class="text-sm text-ink-500">This customer has no vehicles yet — add one.</p>
                        @endforelse
                    </div>
                    @error('vehicle_id') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    </div>

    {{-- 2. job details --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title"><span class="mr-2 text-brand-500">2</span> Job Details</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-4">
                <label class="label">Customer complaint / work requested *</label>
                <textarea wire:model="complaints" rows="3" class="input" placeholder="e.g. Noise from front brakes, service due, AC not cooling"></textarea>
                @error('complaints') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="label">Mechanic</label>
                <select wire:model="mechanic_id" class="input">
                    <option value="">— Assign later —</option>
                    @foreach ($mechanics as $m) <option value="{{ $m->id }}">{{ $m->name }}{{ $m->specialization ? ' — '.$m->specialization : '' }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Priority</label>
                <select wire:model="priority" class="input">@foreach ($priorities as $p) <option>{{ $p }}</option> @endforeach</select>
            </div>
            <div>
                <label class="label">Promised date</label>
                <input type="datetime-local" wire:model="promised_at" class="input">
                @error('promised_at') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="label">Odometer in (km)</label>
                <input type="number" min="0" wire:model="odometer_in" class="input">
                @error('odometer_in') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="label">Fuel level</label>
                <div class="flex gap-2">
                    @foreach ($fuelLevels as $f)
                        <button type="button" wire:click="$set('fuel_level', @js($f))"
                                class="flex-1 rounded-lg border px-2 py-2 text-xs font-bold {{ $fuel_level === $f ? 'border-ink-900 bg-ink-900 text-white' : 'border-ink-200 text-ink-600 hover:bg-ink-50' }}">{{ $f }}</button>
                    @endforeach
                </div>
            </div>
            <div class="sm:col-span-4">
                <label class="label">Inspection notes (condition on arrival, scratches, items in car)</label>
                <textarea wire:model="inspection_notes" rows="2" class="input"></textarea>
            </div>
        </div>
    </div>

    {{-- 3. advance --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title"><span class="mr-2 text-brand-500">3</span> Advance Payment <span class="font-normal normal-case text-ink-400">(optional)</span></h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Amount ({{ setting('currency') }})</label>
                <input type="number" min="0" step="any" wire:model="advance" class="input" placeholder="0">
                @error('advance') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Paid by</label>
                <select wire:model="advance_mode" class="input">@foreach ($modes as $m) <option>{{ $m }}</option> @endforeach</select>
            </div>
            <div>
                <label class="label">Reference (M-Pesa code, cheque no)</label>
                <input wire:model="advance_ref" class="input font-mono uppercase">
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('job-cards.index') }}" wire:navigate class="btn-light">Cancel</a>
        <button type="submit" class="btn-primary px-6" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Open Job Card</span><span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>

    <livewire:vehicles.form />
</form>
