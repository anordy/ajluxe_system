<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-ink-500">Custom orders from <b>Import for you</b> on the website. Send a price, record the deposit, and update the status: customers with an account see it there.</p>
        <a href="{{ route('shop.import') }}" target="_blank" class="btn-light shrink-0 sm:ml-auto">Open the page</a>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach ($tabs as $k => $label)
            <button wire:click="$set('tab', @js($k))" class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold {{ $tab === $k ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-100 hover:bg-ink-50' }}">
                {{ $label }} @if ($k === 'new' && $newCount) <span class="rounded-full bg-brand-500 px-1.5 text-xs text-white">{{ $newCount }}</span> @endif
            </button>
        @endforeach
    </div>

    <div class="relative w-full sm:max-w-sm">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
        <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="IMP number, part, engine code, phone…">
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ref</th><th class="w-full">Wanted</th><th>Car</th><th>Customer</th><th class="text-right">Quote</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($requests as $r)
                    <tr wire:key="im{{ $r->id }}" class="cursor-pointer" wire:click="open({{ $r->id }})">
                        <td class="whitespace-nowrap"><div class="font-mono font-semibold text-ink-900">{{ $r->ref }}</div><div class="text-xs text-ink-400">{{ $r->created_at->format('d M Y') }}</div></td>
                        <td class="max-w-md"><div class="font-semibold text-ink-900">{{ $r->item_type }}@if ($r->quantity > 1) × {{ $r->quantity }}@endif</div><div class="truncate text-sm text-ink-500">{{ $r->description }}</div></td>
                        <td class="whitespace-nowrap">{{ $r->car() }}@if ($r->engine_code)<div class="font-mono text-xs text-ink-400">{{ $r->engine_code }}</div>@endif</td>
                        <td class="whitespace-nowrap"><div>{{ $r->name }}</div><div class="text-xs text-ink-400">{{ $r->phone }}</div></td>
                        <td class="whitespace-nowrap text-right">{{ $r->quote_amount !== null ? money($r->quote_amount) : '—' }}</td>
                        <td class="whitespace-nowrap"><span class="{{ ['new' => 'badge-red', 'quoted' => 'badge-amber', 'arrived' => 'badge-green', 'completed' => 'badge-gray', 'cancelled' => 'badge-gray'][$r->status] ?? 'badge-blue' }}">{{ \App\Models\ImportRequest::STATUSES[$r->status] ?? $r->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-12 text-center text-ink-400">No import requests here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $requests->links() }}</div> @endif
    </div>

    <x-modal show="showView" :title="$view ? $view->ref.' · '.$view->item_type : 'Import request'" max-width="max-w-3xl">
        @if ($view)
            <div class="grid gap-5 text-sm lg:grid-cols-2">
                <dl class="space-y-3 rounded-lg bg-ink-50 p-4">
                    <div><dt class="text-xs font-semibold text-ink-400">Wanted</dt><dd class="whitespace-pre-line font-semibold text-ink-900">{{ $view->description }}</dd></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><dt class="text-xs font-semibold text-ink-400">Car</dt><dd>{{ $view->car() }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-400">Engine code</dt><dd class="font-mono">{{ $view->engine_code ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-400">VIN / chassis</dt><dd class="font-mono">{{ $view->vin ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-400">Quantity</dt><dd>{{ $view->quantity }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-400">From</dt><dd>{{ $view->source_country ?: 'Best price' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-400">Condition</dt><dd>{{ $view->condition ?: 'Any' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-400">Budget</dt><dd>{{ $view->budget ? money($view->budget) : '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-400">Received</dt><dd>{{ $view->created_at->format('d M Y H:i') }}</dd></div>
                    </div>
                    <div class="border-t border-ink-100 pt-3">
                        <dt class="text-xs font-semibold text-ink-400">Customer</dt>
                        <dd class="font-semibold">{{ $view->name }} @if ($view->customer) <span class="badge-gray">Website account</span> @endif</dd>
                        <dd>@if ($view->phone)<a href="tel:{{ $view->phone }}" class="text-brand-600">{{ $view->phone }}</a>@endif @if ($view->email) · <a href="mailto:{{ $view->email }}" class="text-brand-600">{{ $view->email }}</a>@endif</dd>
                        @if ($waLink) <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn-light mt-2 !py-1.5 text-xs">Message on WhatsApp</a> @endif
                    </div>
                </dl>

                @if ($this->canManage())
                    <form wire:submit="save" id="importForm" class="space-y-3">
                        <div>
                            <label class="label">Status</label>
                            <select wire:model="form.status" class="input">
                                @foreach (\App\Models\ImportRequest::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Price delivered (TZS)</label>
                                <input wire:model="form.quote_amount" inputmode="decimal" class="input" placeholder="With shipping & duty">
                                @error('form.quote_amount') <p class="error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Deposit paid</label>
                                <input wire:model="form.deposit_paid" inputmode="decimal" class="input">
                                @error('form.deposit_paid') <p class="error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        @if ($view->balance() !== null) <p class="text-xs text-ink-500">Balance to pay on arrival: <b>{{ money($view->balance()) }}</b> (saved figures)</p> @endif
                        <div>
                            <label class="label">Expected arrival</label>
                            <input type="date" wire:model="form.eta" class="input">
                        </div>
                        <div>
                            <label class="label">Message to the customer</label>
                            <textarea wire:model="form.customer_message" rows="2" maxlength="500" class="input" placeholder="Shown in their account, e.g. “Engine found in Yokohama, tested, 98,000 km.”"></textarea>
                        </div>
                        <div>
                            <label class="label">Staff note</label>
                            <textarea wire:model="form.staff_note" rows="2" maxlength="1000" class="input" placeholder="Supplier, cost, shipping company… (staff only)"></textarea>
                        </div>
                        @if ($view->handler) <p class="text-xs text-ink-400">Last updated by {{ $view->handler->name }}, {{ $view->updated_at->format('d M H:i') }}</p> @endif
                    </form>
                @endif
            </div>
        @endif
        <x-slot:footer>
            @if ($view && $this->canManage())
                @if (auth()->user()->hasRole('admin', 'manager'))
                    <button type="button" class="btn-light mr-auto !text-brand-600" wire:click="delete({{ $view->id }})" wire:confirm="Delete request {{ $view->ref }}?">Delete</button>
                @endif
                <button type="button" class="btn-light" @click="open = false">Cancel</button>
                <button type="submit" form="importForm" class="btn-primary">Save</button>
            @endif
        </x-slot:footer>
    </x-modal>
</div>
