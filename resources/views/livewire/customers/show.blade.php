<div class="space-y-6">
    <a href="{{ route('customers.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-ink-500 hover:text-ink-900">← All customers</a>

    {{-- header --}}
    <div class="card flex flex-col gap-5 p-6 md:flex-row md:items-center">
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-ink-900 text-lg font-extrabold text-white">
            {{ collect(explode(' ', $customer->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-extrabold text-ink-900">{{ $customer->name }}</h2>
                <span class="badge-gray">{{ $customer->type }}</span>
                @unless ($customer->is_active) <span class="badge-red">Inactive</span> @endunless
            </div>
            <p class="mt-1 font-mono text-xs text-ink-400">{{ $customer->code }}</p>
        </div>
        <div class="flex gap-6 md:text-right">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Balance owed</p>
                <p class="text-xl font-extrabold {{ $balance > 0 ? 'text-brand-600' : 'text-ink-900' }}">{{ money($balance) }}</p>
            </div>
            @if ((float) $customer->credit_limit > 0)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Credit limit</p>
                    <p class="text-xl font-extrabold text-ink-900">{{ money($customer->credit_limit) }}</p>
                </div>
            @endif
        </div>
        @if ($this->canManage())
            <button class="btn-light" wire:click="edit"><x-icon name="pencil" class="h-4 w-4" /> Edit</button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- details --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Contact Details</h3></div>
            <dl class="divide-y divide-ink-100 text-sm">
                @foreach ([
                    'Phone' => $customer->phone, 'Other phone' => $customer->alt_phone, 'Email' => $customer->email,
                    'Address' => $customer->address, 'TIN' => $customer->tin,
                    'Opening balance' => (float) $customer->opening_balance ? money($customer->opening_balance) : null,
                ] as $label => $value)
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-ink-500">{{ $label }}</dt>
                        <dd class="text-right font-medium text-ink-900">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($customer->notes)
                <div class="border-t border-ink-100 px-5 py-4 text-sm text-ink-600"><p class="label">Notes</p>{{ $customer->notes }}</div>
            @endif
        </div>

        {{-- vehicles --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">Vehicles <span class="ml-1 text-ink-400">{{ $vehicles->count() }}</span></h3>
                @if ($this->canManage())
                    <button class="btn-dark btn-sm" wire:click="$dispatch('open-vehicle-form', { customerId: {{ $customer->id }} })"><x-icon name="plus" class="h-4 w-4" /> Add Vehicle</button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Reg No</th><th>Make / Model</th><th>Year</th><th>Fuel</th><th>Odometer</th><th class="text-right"></th></tr></thead>
                    <tbody>
                    @forelse ($vehicles as $v)
                        <tr wire:key="v{{ $v->id }}">
                            <td><span class="rounded-md border-2 border-ink-900 bg-amber-300 px-2 py-0.5 font-mono text-xs font-bold tracking-wider text-ink-900">{{ $v->reg_no }}</span></td>
                            <td class="font-medium">{{ trim($v->make.' '.$v->model) ?: '—' }}</td>
                            <td>{{ $v->year ?: '—' }}</td>
                            <td>{{ $v->fuel_type ?: '—' }}</td>
                            <td>{{ $v->odometer ? number_format($v->odometer).' km' : '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                @if ($this->canManage())
                                    <button class="btn-icon" title="Edit" wire:click="$dispatch('open-vehicle-form', { id: {{ $v->id }} })"><x-icon name="pencil" class="h-4 w-4" /></button>
                                    <button class="btn-icon hover:!text-brand-600" title="Remove" wire:click="deleteVehicle({{ $v->id }})" wire:confirm="Remove vehicle {{ $v->reg_no }}?"><x-icon name="trash" class="h-4 w-4" /></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-ink-400">No vehicles yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Job Cards <span class="ml-1 rounded-full bg-ink-100 px-2 py-0.5 text-xs font-bold text-ink-600">{{ $jobs->count() }}</span></h3>
                @if ($canJobs && $jobs->count())
                    <a href="{{ route('job-cards.index', ['customer' => $customer->id, 'tab' => 'all']) }}" wire:navigate class="ml-auto mr-2 text-xs font-semibold text-ink-500 hover:text-ink-900">Open in job card list</a>
                @endif
                @if ($canJobs)
                    <a href="{{ route('job-cards.create', ['customer' => $customer->id]) }}" wire:navigate class="btn-dark btn-sm"><x-icon name="plus" class="h-4 w-4" /> New Job Card</a>
                @endif
            </div>
            <ul class="max-h-[28rem] divide-y divide-ink-100 overflow-y-auto text-sm">
                @forelse ($jobs as $j)
                    <li wire:key="cj{{ $j->id }}">
                        <a @if ($canJobs) href="{{ route('job-cards.show', $j) }}" wire:navigate @endif class="flex items-center justify-between gap-3 px-5 py-3 {{ $canJobs ? 'hover:bg-ink-50' : '' }}">
                            <div>
                                <p class="font-mono text-xs font-bold text-ink-900">{{ $j->number }} <span class="ml-1 font-sans font-normal text-ink-500">{{ $j->vehicle?->reg_no }}</span></p>
                                <p class="text-xs text-ink-400">{{ $j->received_at->format('d M Y') }}</p>
                            </div>
                            <div class="text-right"><span class="badge {{ $j->badge() }}">{{ $j->status }}</span><p class="mt-1 text-xs font-semibold">{{ money($j->grand_total) }}</p></div>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-ink-400">No job cards yet.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Invoices <span class="ml-1 rounded-full bg-ink-100 px-2 py-0.5 text-xs font-bold text-ink-600">{{ $invoices->count() }}</span></h3></div>
            <ul class="max-h-[28rem] divide-y divide-ink-100 overflow-y-auto text-sm">
                @forelse ($invoices as $inv)
                    <li class="flex items-center justify-between gap-3 px-5 py-3" wire:key="ci{{ $inv->id }}">
                        <div>
                            <a href="{{ route('invoices.show', $inv) }}" wire:navigate class="font-mono text-xs font-bold text-ink-900 hover:text-brand-600">{{ $inv->number }}</a>
                            <p class="text-xs text-ink-400">{{ $inv->date->format('d M Y') }} · {{ money($inv->grand_total) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right">
                                <span class="{{ \App\Models\Invoice::STATUS_BADGES[$inv->status] }}">{{ \App\Models\Invoice::STATUS_LABELS[$inv->status] }}</span>
                                @if ((float) $inv->balance > 0 && $inv->status !== 'cancelled') <p class="mt-1 text-xs font-bold text-brand-600">{{ money($inv->balance) }} due</p> @endif
                            </div>
                            @if ((float) $inv->balance > 0 && $inv->status !== 'cancelled')
                                <button class="btn-primary btn-sm" wire:click="$dispatch('open-payment-form', { invoiceId: {{ $inv->id }} })">Pay</button>
                            @endif
                            <a href="{{ route('print.invoice', $inv) }}" target="_blank" class="btn-icon" title="Print"><x-icon name="printer" class="h-4 w-4" /></a>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-ink-400">No invoices yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <livewire:payments.record-form />

    <livewire:vehicles.form />

    <x-modal show="showForm" title="Edit Customer" max-width="max-w-2xl">
        <form wire:submit="save" id="customerForm">@include('livewire.customers._form')</form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="customerForm" class="btn-primary" wire:loading.attr="disabled">Save Customer</button>
        </x-slot:footer>
    </x-modal>
</div>
