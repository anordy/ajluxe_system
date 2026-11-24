@php
    $counter = $inv->isCounter();
    $channel = $inv->jobCard ? 'Garage job' : ($order ? 'Website order' : 'Shop counter (POS)');
    $cancelled = $inv->status === \App\Models\Invoice::CANCELLED;
    $c = $inv->customer;
@endphp
<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('invoices.index', $counter ? ['kind' => 'counter'] : ['kind' => 'garage']) }}" wire:navigate class="text-sm font-semibold text-ink-500 hover:text-ink-900">← {{ $counter ? 'All spare parts sales' : 'All garage invoices' }}</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="font-mono text-2xl font-extrabold text-ink-900">{{ $inv->number }}</h2>
                <span class="{{ \App\Models\Invoice::STATUS_BADGES[$inv->status] }}">{{ \App\Models\Invoice::STATUS_LABELS[$inv->status] }}</span>
                <span class="badge-gray">{{ $channel }}</span>
            </div>
            <p class="mt-1 text-sm text-ink-500">{{ $inv->date->format('l, d M Y') }} · sold by {{ $inv->creator?->name ?? '—' }} · recorded {{ $inv->created_at->format('d M Y, H:i') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPay && (float) $inv->balance > 0 && ! $cancelled)
                <button class="btn-primary btn-sm" wire:click="$dispatch('open-payment-form', { invoiceId: {{ $inv->id }} })"><x-icon name="money" class="h-4 w-4" /> Record payment</button>
            @endif
            @if ($counter) <a href="{{ route('print.thermal', $inv) }}" target="_blank" class="btn-dark btn-sm"><x-icon name="printer" class="h-4 w-4" /> Receipt (80mm)</a> @endif
            <a href="{{ route('print.invoice', $inv) }}" target="_blank" class="btn-light btn-sm"><x-icon name="printer" class="h-4 w-4" /> A4 invoice</a>
            @if ($canManage && ! $cancelled)
                <a href="{{ route('returns.index', ['invoice' => $inv->number]) }}" wire:navigate class="btn-light btn-sm"><x-icon name="return" class="h-4 w-4" /> Return items</a>
            @endif
        </div>
    </div>

    @if ($cancelled)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><b>This sale was cancelled.</b> @if ($inv->void_reason) Reason: {{ $inv->void_reason }} @endif</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- what was bought --}}
        <div class="card overflow-hidden xl:col-span-2">
            <div class="card-header"><h3 class="card-title">What was bought</h3><span class="text-sm text-ink-500">{{ $inv->items->count() }} {{ $inv->items->count() === 1 ? 'line' : 'lines' }}</span></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($inv->items as $it)
                        <tr wire:key="it{{ $it->id }}">
                            <td>
                                <div class="font-medium text-ink-900">{{ $it->description }}</div>
                                <div class="text-xs text-ink-400">
                                    @if ($it->part) <span class="font-mono">{{ $it->part->sku }}</span>@if ($it->part->part_number) · {{ $it->part->part_number }}@endif @if ($it->part->brand) · {{ $it->part->brand }}@endif
                                    @elseif ($it->service_id) Service / labour @endif
                                    @if ($it->part_id && ($returned[$it->part_id] ?? 0) > 0)
                                        <span class="ml-1 font-semibold text-amber-700">· {{ rtrim(rtrim(number_format($returned[$it->part_id], 2), '0'), '.') }} returned</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-right">{{ rtrim(rtrim(number_format($it->qty, 2), '0'), '.') }} {{ $it->part?->unit }}</td>
                            <td class="whitespace-nowrap text-right">{{ amount($it->rate) }}</td>
                            <td class="whitespace-nowrap text-right font-semibold">{{ amount($it->amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <dl class="ml-auto max-w-sm space-y-1.5 border-t border-ink-100 p-5 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Subtotal</dt><dd>{{ money($inv->subtotal) }}</dd></div>
                @if ((float) $inv->discount) <div class="flex justify-between"><dt class="text-ink-500">Discount</dt><dd>− {{ money($inv->discount) }}</dd></div> @endif
                @if ((float) $inv->tax_amount) <div class="flex justify-between"><dt class="text-ink-500">Tax</dt><dd>{{ money($inv->tax_amount) }}</dd></div> @endif
                @if ((float) $inv->other_charges) <div class="flex justify-between"><dt class="text-ink-500">Other charges</dt><dd>{{ money($inv->other_charges) }}</dd></div> @endif
                <div class="flex justify-between border-t border-ink-100 pt-2 text-base font-extrabold"><dt>Total</dt><dd>{{ money($inv->grand_total) }}</dd></div>
                @if ((float) $inv->credited) <div class="flex justify-between text-amber-700"><dt>Returned (credit)</dt><dd>− {{ money($inv->credited) }}</dd></div> @endif
                <div class="flex justify-between"><dt class="text-ink-500">Paid</dt><dd class="font-semibold text-emerald-700">{{ money($inv->paid) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Balance</dt><dd class="font-bold {{ (float) $inv->balance > 0 && ! $cancelled ? 'text-brand-600' : 'text-ink-400' }}">{{ money($inv->balance) }}</dd></div>
            </dl>
        </div>

        <div class="space-y-6">
            {{-- customer --}}
            <div class="card p-5">
                <h3 class="card-title mb-3">Customer</h3>
                @if ($c)
                    <p class="text-lg font-bold text-ink-900">{{ $c->name }}</p>
                    <dl class="mt-2 space-y-1.5 text-sm">
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-ink-400">Code</dt><dd class="font-mono">{{ $c->code }}</dd></div>
                        @if ($c->phone) <div class="flex gap-2"><dt class="w-20 shrink-0 text-ink-400">Phone</dt><dd><a href="tel:{{ $c->phone }}" class="hover:underline">{{ $c->phone }}</a>@if ($c->alt_phone) · {{ $c->alt_phone }}@endif</dd></div> @endif
                        @if ($c->email) <div class="flex gap-2"><dt class="w-20 shrink-0 text-ink-400">Email</dt><dd class="break-all">{{ $c->email }}</dd></div> @endif
                        @if ($c->address) <div class="flex gap-2"><dt class="w-20 shrink-0 text-ink-400">Address</dt><dd>{{ $c->address }}</dd></div> @endif
                        @if ($c->tin) <div class="flex gap-2"><dt class="w-20 shrink-0 text-ink-400">TIN</dt><dd>{{ $c->tin }}</dd></div> @endif
                        @php $owes = $c->balance(); @endphp
                        @if ($owes > 0) <div class="flex gap-2"><dt class="w-20 shrink-0 text-ink-400">Owes in all</dt><dd class="font-bold text-brand-600">{{ money($owes) }}</dd></div> @endif
                    </dl>
                    <a href="{{ route('customers.show', $c) }}" wire:navigate class="btn-light btn-sm mt-4 w-full justify-center">Open customer · all purchases</a>
                @else
                    <p class="text-lg font-bold text-ink-900">{{ $inv->walk_in_name ?: 'Walk-in customer' }}</p>
                    <p class="mt-1 text-sm text-ink-500">Not saved as a customer.</p>
                @endif
            </div>

            {{-- where it came from --}}
            @if ($inv->jobCard || $order)
                <div class="card p-5 text-sm">
                    <h3 class="card-title mb-3">{{ $inv->jobCard ? 'Garage job' : 'Website order' }}</h3>
                    @if ($inv->jobCard)
                        <a href="{{ route('job-cards.show', $inv->jobCard) }}" wire:navigate class="font-mono font-bold text-ink-900 hover:text-brand-600">{{ $inv->jobCard->number }}</a>
                        <p class="mt-1 text-ink-500">{{ $inv->jobCard->vehicle?->reg_no }} · {{ trim($inv->jobCard->vehicle?->make.' '.$inv->jobCard->vehicle?->model) }}</p>
                    @else
                        <a href="{{ route('orders.index', ['search' => $order->number]) }}" wire:navigate class="font-mono font-bold text-ink-900 hover:text-brand-600">{{ $order->number }}</a>
                        <p class="mt-1 text-ink-500">{{ \App\Models\Order::FULFILMENT[$order->fulfilment] ?? $order->fulfilment }} · {{ $order->contact_phone }}</p>
                        @if ($order->delivery_address) <p class="mt-1 text-ink-500">{{ $order->delivery_address }}</p> @endif
                    @endif
                </div>
            @endif

            {{-- payments --}}
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Payments</h3></div>
                <ul class="divide-y divide-ink-100 text-sm">
                    @forelse ($inv->payments as $p)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="pay{{ $p->id }}">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold {{ (float) $p->amount < 0 ? 'text-brand-600' : 'text-ink-900' }}">{{ money($p->amount) }} <span class="font-normal text-ink-500">· {{ $p->mode }}</span></p>
                                <p class="text-xs text-ink-400"><span class="font-mono">{{ $p->number }}</span> · {{ $p->date->format('d M Y') }}@if ($p->reference) · ref {{ $p->reference }}@endif · {{ $p->creator?->name ?? '—' }}</p>
                            </div>
                            <a href="{{ route('print.receipt', $p) }}" target="_blank" class="btn-icon" title="Print receipt" aria-label="Print receipt {{ $p->number }}"><x-icon name="printer" class="h-4 w-4" /></a>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-ink-400">No payment yet.</li>
                    @endforelse
                </ul>
            </div>

            @if ($inv->returns->isNotEmpty())
                <div class="card p-5 text-sm">
                    <h3 class="card-title mb-3">Returns</h3>
                    @foreach ($inv->returns as $r)
                        <p><span class="font-mono font-semibold">{{ $r->number }}</span> · {{ $r->date?->format('d M Y') }} · {{ $r->items->count() }} {{ $r->items->count() === 1 ? 'line' : 'lines' }}</p>
                    @endforeach
                </div>
            @endif

            @if ($inv->notes)
                <div class="card p-5 text-sm"><h3 class="card-title mb-2">Notes</h3><p class="whitespace-pre-line text-ink-600">{{ $inv->notes }}</p></div>
            @endif
        </div>
    </div>

    <livewire:payments.record-form />
</div>
