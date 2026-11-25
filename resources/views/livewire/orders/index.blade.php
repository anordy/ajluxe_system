<div class="space-y-5">
    <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach (['open' => ['Open', ($counts['pending'] ?? 0) + ($counts['confirmed'] ?? 0) + ($counts['ready'] ?? 0)], 'pending' => ['New', $counts['pending'] ?? 0], 'ready' => ['Ready', $counts['ready'] ?? 0], 'completed' => ['Completed', $counts['completed'] ?? 0], 'cancelled' => ['Cancelled', $counts['cancelled'] ?? 0], 'all' => ['All', $counts->sum()]] as $k => [$label, $n])
            <button wire:click="$set('tab', @js($k))" class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold {{ $tab === $k ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-100 hover:bg-ink-50' }}">
                {{ $label }} <span class="rounded-full px-1.5 text-xs {{ $tab === $k ? 'bg-white/15' : ($k === 'pending' && $n ? 'bg-brand-500 text-white' : 'bg-ink-100') }}">{{ $n }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Order no, customer, phone…">
        </div>
        <a href="{{ route('shop.home') }}" target="_blank" class="btn-light sm:ml-auto">Open the website</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Order</th><th class="w-full">Customer</th><th>Getting it</th><th>Paying by</th><th class="text-right">Items</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr wire:key="o{{ $o->id }}" class="cursor-pointer" wire:click="open({{ $o->id }})">
                        <td class="whitespace-nowrap"><div class="font-mono text-xs font-bold text-ink-900">{{ $o->number }}</div><div class="text-xs text-ink-400">{{ $o->created_at->format('d M Y H:i') }}</div></td>
                        <td><div class="font-medium">{{ $o->shopCustomer?->name }}</div><div class="text-xs text-ink-400">{{ $o->contact_phone }}</div></td>
                        <td class="whitespace-nowrap">{{ $o->fulfilment === 'delivery' ? 'Delivery' : 'Collect' }}</td>
                        <td class="whitespace-nowrap">{{ \App\Models\Order::PAYMENT_METHODS[$o->payment_method] ?? $o->payment_method }} @if ($o->payment_reference) <div class="font-mono text-xs text-ink-400">{{ $o->payment_reference }}</div> @endif</td>
                        <td class="text-right">{{ $o->items_count }}</td>
                        <td class="text-right font-semibold">{{ amount($o->total) }}</td>
                        <td class="whitespace-nowrap"><span class="{{ $o->badge() }}">{{ $o->status === 'pending' ? 'New' : ($o->status === 'ready' ? $o->readyLabel() : $o->label()) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-ink-400">No online orders here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $orders->links() }}</div> @endif
    </div>

    <x-modal show="showView" :title="$view ? 'Order '.$view->number : 'Order'" max-width="max-w-3xl">
        @if ($view)
            <div class="space-y-5">
                <div class="grid gap-4 rounded-lg bg-ink-50 p-4 text-sm sm:grid-cols-3">
                    <div><p class="text-xs font-semibold uppercase text-ink-400">Customer</p><p class="font-semibold">{{ $view->shopCustomer?->name }}</p><p>{{ $view->contact_phone }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-ink-400">Getting it</p><p class="font-semibold">{{ \App\Models\Order::FULFILMENT[$view->fulfilment] }}</p><p>{{ $view->delivery_address }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-ink-400">Paying by</p><p class="font-semibold">{{ \App\Models\Order::PAYMENT_METHODS[$view->payment_method] }}</p><p class="font-mono">{{ $view->payment_reference }}</p></div>
                </div>
                @if ($view->notes) <p class="rounded-lg border border-ink-100 p-3 text-sm"><b>Customer note:</b> {{ $view->notes }}</p> @endif

                <table class="table">
                    <thead><tr><th class="w-full">Part</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Amount</th>@if ($view->status === 'pending') <th class="text-right">In stock</th> @endif</tr></thead>
                    <tbody>
                    @foreach ($view->items as $i)
                        <tr>
                            <td>{{ $i->name }} <div class="font-mono text-xs text-ink-400">{{ $i->part?->sku }} {{ $i->part?->rack_location ? '· '.$i->part->rack_location : '' }}</div>
                                @if ($i->part?->sourced)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 ring-1 ring-sky-200">
                                        <x-icon name="truck" class="h-3.5 w-3.5" /> From supplier: {{ $i->part->sourceSupplier?->name ?? '—' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">{{ $i->qtyLabel() }}</td>
                            <td class="text-right">{{ amount($i->price) }} @if ((float) $i->discount_percent > 0) <div class="text-xs text-brand-600">−{{ (int) $i->discount_percent }}%</div> @endif</td>
                            <td class="text-right font-semibold">{{ amount($i->amount) }}</td>
                            @if ($view->status === 'pending')
                                @php $s = max(0, (float) ($i->part?->stock_qty ?? 0)); $buy = $i->part?->sourced ? max(0, (float) $i->qty - $s) : 0; @endphp
                                <td class="whitespace-nowrap text-right font-semibold {{ $buy > 0 ? 'text-sky-700' : ($s < (float) $i->qty ? 'text-brand-600' : 'text-emerald-700') }}">
                                    {{ rtrim(rtrim(number_format($s, 2), '0'), '.') }}
                                    @if ($buy > 0) <div class="text-xs font-normal">buy {{ rtrim(rtrim(number_format($buy, 2), '0'), '.') }}</div> @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    <tr><td colspan="3" class="text-right font-bold">Total</td><td class="text-right font-extrabold">{{ money($view->total) }}</td>@if ($view->status === 'pending') <td></td> @endif</tr>
                    </tbody>
                </table>

                @error('action') <p class="rounded-lg bg-brand-50 p-3 text-sm font-semibold text-brand-700">{{ $message }}</p> @enderror

                @if ($view->status === 'pending')
                    <div class="rounded-lg border border-ink-100 p-4">
                        <p class="text-sm font-semibold">Confirm the order</p>
                        <p class="text-xs text-ink-500">Creates a spare parts sale for this customer and takes the parts out of stock. Enter what was already paid (0 if they pay later).</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div><label class="label">Already paid</label><input type="number" min="0" step="any" wire:model="paid" class="input" placeholder="0"></div>
                            <div><label class="label">Paid by</label><select wire:model="mode" class="input">@foreach ($modes as $m) <option>{{ $m }}</option> @endforeach</select></div>
                            <div><label class="label">Reference</label><input wire:model="reference" class="input font-mono uppercase"></div>
                        </div>
                        @error('paid') <p class="error">{{ $message }}</p> @enderror
                        <button class="btn-primary mt-3" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">Confirm &amp; create sale</button>
                    </div>
                @elseif ($view->invoice)
                    <p class="text-sm">Sale <span class="font-mono font-bold">{{ $view->invoice->number }}</span> — paid {{ money($view->invoice->paid) }}, balance <b class="{{ (float) $view->invoice->balance > 0 ? 'text-brand-600' : '' }}">{{ money($view->invoice->balance) }}</b>.
                        <a href="{{ route('invoices.index', ['search' => $view->invoice->number]) }}" wire:navigate class="font-semibold text-brand-600 hover:underline">Record payment</a> ·
                        <a href="{{ route('print.thermal', $view->invoice) }}" target="_blank" class="font-semibold text-brand-600 hover:underline">Receipt</a></p>
                @endif

                @if ($view->isOpen())
                    <div class="flex flex-wrap items-end gap-3 border-t border-ink-100 pt-4">
                        @if ($view->status === 'confirmed') <button class="btn-dark" wire:click="ready" wire:loading.attr="disabled">Mark {{ strtolower($view->readyLabel()) }}</button> @endif
                        @if (in_array($view->status, ['confirmed', 'ready'])) <button class="btn-dark" wire:click="complete" wire:loading.attr="disabled" wire:confirm="Mark as {{ $view->fulfilment === 'delivery' ? 'delivered' : 'collected' }}?">{{ $view->fulfilment === 'delivery' ? 'Delivered' : 'Collected' }}</button> @endif
                        <div class="ml-auto flex items-end gap-2">
                            <div><label class="label">Cancel reason</label><input wire:model="cancelReason" class="input" placeholder="e.g. Customer changed mind"></div>
                            <button class="btn-danger" wire:click="cancel" wire:loading.attr="disabled" wire:confirm="Cancel this order?{{ $view->invoice ? ' The sale will be voided and stock returned.' : '' }}">Cancel order</button>
                        </div>
                    </div>
                    @error('cancelReason') <p class="error">{{ $message }}</p> @enderror
                @elseif ($view->status === 'cancelled')
                    <p class="text-sm text-ink-500">Cancelled: {{ $view->cancel_reason }}</p>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Close</button>
        </x-slot:footer>
    </x-modal>
</div>
