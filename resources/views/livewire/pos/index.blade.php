<div class="grid gap-6 xl:grid-cols-5">
    {{-- ===== products ===== --}}
    <div class="space-y-4 xl:col-span-3">
        <div class="flex items-center gap-3">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-3 h-5 w-5 text-ink-300" />
                <input wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="addFirst" autofocus autocomplete="off"
                       class="input py-3 pl-11 text-base" placeholder="Search part name, SKU, OEM no, brand… (Enter adds an exact SKU)">
            </div>
            <div class="hidden text-right text-xs text-ink-500 sm:block">
                <p class="font-semibold uppercase tracking-wide text-ink-400">Today</p>
                <p><b class="text-ink-900">{{ $todayCount }}</b> sales · <b class="text-ink-900">{{ money($todayTotal) }}</b></p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($results as $p)
                @php $out = (float) $p->stock_qty <= 0; @endphp
                <button type="button" wire:key="pp{{ $p->id }}" wire:click="add({{ $p->id }})" @disabled($out)
                        class="card flex flex-col items-start p-4 text-left transition {{ $out ? 'cursor-not-allowed opacity-50' : 'hover:-translate-y-0.5 hover:shadow-md hover:ring-2 hover:ring-ink-900' }}">
                    <span class="font-mono text-[10px] text-ink-400">{{ $p->sku }}{{ $p->oem_no ? ' · '.$p->oem_no : '' }}</span>
                    <span class="mt-0.5 line-clamp-2 font-semibold leading-snug text-ink-900">{{ $p->name }}</span>
                    <span class="text-xs text-ink-500">{{ $p->brand }}</span>
                    <div class="mt-3 flex w-full items-end justify-between">
                        <span class="text-base font-extrabold text-ink-900">{{ amount($p->selling_price) }}</span>
                        <span class="text-xs font-semibold {{ $out ? 'text-brand-600' : ($p->isLow() ? 'text-amber-600' : 'text-ink-500') }}">
                            {{ $out ? 'Out of stock' : rtrim(rtrim(number_format($p->stock_qty, 2), '0'), '.').' '.$p->unit }}
                        </span>
                    </div>
                </button>
            @empty
                <div class="card col-span-full p-10 text-center text-ink-400">No parts found.</div>
            @endforelse
        </div>

        {{-- ===== recent sales: open any one to see exactly what was bought ===== --}}
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">Recent sales</h3>
                <a href="{{ route('invoices.index', ['kind' => 'counter']) }}" wire:navigate class="text-sm font-semibold text-ink-500 hover:text-ink-900">All spare parts sales</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Sale</th><th>Customer</th><th>Items</th><th class="text-right">Total</th><th>Status</th><th class="text-right"><span class="sr-only">View</span></th></tr></thead>
                    <tbody>
                    @forelse ($recent as $r)
                        <tr wire:key="rs{{ $r->id }}" class="cursor-pointer" onclick="Livewire.navigate('{{ route('invoices.show', $r) }}')">
                            <td class="whitespace-nowrap"><div class="font-mono text-xs font-bold text-ink-900">{{ $r->number }}</div><div class="text-xs text-ink-400">{{ $r->date?->isToday() ? 'Today' : $r->date?->format('d M') }}{{ $r->created_at ? ', '.$r->created_at->format('H:i') : '' }}</div></td>
                            <td><div class="font-medium">{{ $r->customerName() }}</div>@if ($r->customer?->phone) <div class="text-xs text-ink-400">{{ $r->customer->phone }}</div> @endif</td>
                            <td class="max-w-[260px] text-xs text-ink-500"><span class="line-clamp-2">{{ $r->items->map(fn ($i) => rtrim(rtrim(number_format($i->qty, 2), '0'), '.').' × '.$i->description)->implode(', ') }}</span></td>
                            <td class="whitespace-nowrap text-right font-semibold">{{ amount($r->grand_total) }}</td>
                            <td><span class="{{ \App\Models\Invoice::STATUS_BADGES[$r->status] ?? 'badge-gray' }}">{{ \App\Models\Invoice::STATUS_LABELS[$r->status] ?? ucfirst((string) $r->status) }}</span></td>
                            <td class="text-right" onclick="event.stopPropagation()"><a href="{{ route('invoices.show', $r) }}" wire:navigate class="btn-icon" title="View sale" aria-label="View sale {{ $r->number }}"><x-icon name="eye" class="h-4 w-4" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-400">No sales yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== cart ===== --}}
    <div class="xl:col-span-2">
        <div class="card sticky top-20 flex flex-col">
            @if ($lastSale)
                <div class="m-4 rounded-xl bg-emerald-50 p-4">
                    <p class="font-bold text-emerald-800">Sale {{ $lastSale->number }} completed — {{ money($lastSale->grand_total) }}</p>
                    @if ((float) $lastSale->balance > 0) <p class="text-sm font-semibold text-brand-600">Balance on account: {{ money($lastSale->balance) }}</p> @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('print.thermal', $lastSale) }}" target="_blank" class="btn-dark btn-sm"><x-icon name="printer" class="h-4 w-4" /> Receipt (80mm)</a>
                        <a href="{{ route('print.invoice', $lastSale) }}" target="_blank" class="btn-light btn-sm"><x-icon name="printer" class="h-4 w-4" /> A4 Invoice</a>
                        <a href="{{ route('invoices.show', $lastSale) }}" wire:navigate class="btn-light btn-sm"><x-icon name="eye" class="h-4 w-4" /> View sale</a>
                    </div>
                </div>
            @endif

            <div class="card-header">
                <h3 class="card-title">Cart <span class="ml-1 text-ink-400">{{ count($cart) }}</span></h3>
                @if ($cart) <button class="text-xs font-semibold text-ink-400 hover:text-brand-600" wire:click="clear" wire:confirm="Clear the cart?">Clear</button> @endif
            </div>

            <div class="max-h-[38vh] divide-y divide-ink-100 overflow-y-auto">
                @forelse ($cart as $key => $l)
                    <div class="flex items-center gap-3 px-5 py-3" wire:key="c{{ $key }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-ink-900">{{ $l['name'] }}</p>
                            <div class="mt-1 flex items-center gap-1 text-xs text-ink-500">
                                @if ($this->canEditPrice())
                                    <span>@</span><input type="number" step="any" min="0" value="{{ $l['rate'] }}" wire:change="setRate('{{ $key }}', $event.target.value)"
                                           class="w-24 rounded border-ink-200 px-1.5 py-0.5 text-xs">
                                @else
                                    <span>@ {{ amount($l['rate']) }}</span>
                                @endif
                                <span class="ml-1">· {{ rtrim(rtrim(number_format($l['stock'], 2), '0'), '.') }} in stock</span>
                            </div>
                        </div>
                        <div class="flex items-center rounded-lg ring-1 ring-ink-200">
                            <button class="px-2 py-1 text-lg leading-none text-ink-500 hover:text-ink-900" wire:click="setQty('{{ $key }}', {{ $l['qty'] - 1 }})">−</button>
                            <input type="number" step="any" min="0" value="{{ $l['qty'] + 0 }}" wire:change="setQty('{{ $key }}', $event.target.value)"
                                   class="w-12 border-0 p-0 text-center text-sm font-bold focus:ring-0">
                            <button class="px-2 py-1 text-lg leading-none text-ink-500 hover:text-ink-900" wire:click="setQty('{{ $key }}', {{ $l['qty'] + 1 }})">+</button>
                        </div>
                        <p class="w-24 text-right text-sm font-bold">{{ amount($l['qty'] * $l['rate']) }}</p>
                        <button class="text-ink-300 hover:text-brand-600" wire:click="remove('{{ $key }}')"><x-icon name="x" class="h-4 w-4" /></button>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-400">Tap a part to add it to the sale.</p>
                @endforelse
            </div>

            <form wire:submit="checkout" class="space-y-4 border-t border-ink-100 p-5">
                {{-- customer --}}
                <div>
                    <label class="label">Customer</label>
                    @if ($customer)
                        <div class="flex items-center justify-between rounded-lg bg-ink-50 px-3 py-2 text-sm">
                            <span><b>{{ $customer->name }}</b> <span class="text-ink-500">{{ $customer->phone }}</span></span>
                            <button type="button" class="text-xs font-semibold text-brand-600" wire:click="pickCustomer(null)">Change</button>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <input wire:model.live.debounce.250ms="customerSearch" class="input" placeholder="Saved customer…" autocomplete="off">
                                @if (strlen(trim($customerSearch)) >= 2)
                                    <div class="absolute z-10 mt-1 w-64 overflow-hidden rounded-lg border border-ink-100 bg-white shadow-lg">
                                        @forelse ($matches as $m)
                                            <button type="button" wire:click="pickCustomer({{ $m->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-ink-50">{{ $m->name }} <span class="text-xs text-ink-400">{{ $m->phone }}</span></button>
                                        @empty <p class="px-3 py-2 text-sm text-ink-400">No match.</p> @endforelse
                                    </div>
                                @endif
                            </div>
                            <input wire:model="walk_in_name" class="input" placeholder="or walk-in name">
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @if ($this->canEditPrice())
                        <div><label class="label">Discount</label><input type="number" step="any" min="0" wire:model.live.debounce.400ms="discount" class="input"></div>
                        <div><label class="label">Tax %</label><input type="number" step="any" min="0" max="100" wire:model.live.debounce.400ms="tax_rate" class="input"></div>
                    @endif
                </div>

                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">Subtotal</dt><dd>{{ money($totals['subtotal']) }}</dd></div>
                    @if ($totals['discount'] > 0) <div class="flex justify-between"><dt class="text-ink-500">Discount</dt><dd>− {{ money($totals['discount']) }}</dd></div> @endif
                    @if ($totals['tax'] > 0) <div class="flex justify-between"><dt class="text-ink-500">Tax</dt><dd>{{ money($totals['tax']) }}</dd></div> @endif
                    <div class="flex justify-between border-t border-ink-200 pt-2 text-xl"><dt class="font-bold">Total</dt><dd class="font-extrabold">{{ money($totals['total']) }}</dd></div>
                </dl>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Amount paid</label>
                        <input type="number" step="any" min="0" wire:model="paid" class="input font-bold" placeholder="{{ amount($totals['total']) }} (full)">
                    </div>
                    <div>
                        <label class="label">Paid by</label>
                        <select wire:model.live="mode" class="input">@foreach ($modes as $m) <option>{{ $m }}</option> @endforeach</select>
                    </div>
                </div>
                @if ($mode !== 'Cash')
                    <input wire:model="reference" class="input font-mono uppercase" placeholder="Reference (M-Pesa code, slip no)">
                @endif
                <p class="hint">Leave "Amount paid" empty for full payment. A part payment leaves a balance on a saved customer's account.</p>

                <button type="submit" class="btn-primary w-full py-3 text-base" @disabled(! $cart) wire:loading.attr="disabled" wire:target="checkout">
                    <span wire:loading.remove wire:target="checkout">Complete Sale · {{ money($totals['total']) }}</span>
                    <span wire:loading wire:target="checkout">Saving…</span>
                </button>
            </form>
        </div>
    </div>
</div>
