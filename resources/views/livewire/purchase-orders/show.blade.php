@php
    use App\Models\PurchaseOrder;
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $owing = in_array($po->status, PurchaseOrder::OWING) && (float) $po->balance > 0;
@endphp
<div class="space-y-6">
    <a href="{{ route('purchase-orders.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-ink-500 hover:text-ink-900">← Purchase orders</a>

    <div class="card flex flex-col gap-5 p-6 lg:flex-row lg:items-center">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-mono text-xl font-extrabold text-ink-900">{{ $po->number }}</h2>
                <span class="{{ $po->badge() }}">{{ $po->label() }}</span>
            </div>
            <p class="mt-1 text-sm"><b>{{ $po->supplier->name }}</b> <span class="text-ink-500">{{ $po->supplier->phone }}</span></p>
            <p class="text-xs text-ink-400">Ordered {{ $po->date->format('d M Y') }} {{ $po->expected_date ? '· expected '.$po->expected_date->format('d M Y') : '' }}
                {{ $po->supplier_invoice_no ? '· supplier invoice '.$po->supplier_invoice_no : '' }} · by {{ $po->creator?->name ?? '—' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('print.purchase-order', $po) }}" target="_blank" class="btn-light btn-sm"><x-icon name="printer" class="h-4 w-4" /> Print</a>
            @if ($po->isDraft())
                <a href="{{ route('purchase-orders.edit', $po) }}" wire:navigate class="btn-light btn-sm"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                <button class="btn-dark btn-sm" wire:click="markOrdered" wire:loading.attr="disabled">Mark as ordered</button>
            @endif
            @if ($po->canReceive()) <button class="btn-primary btn-sm" wire:click="openReceive">Receive goods</button> @endif
            @if ($owing) <button class="btn-dark btn-sm" wire:click="openPay">Pay supplier</button> @endif
            @if (in_array($po->status, [PurchaseOrder::PARTIAL, PurchaseOrder::RECEIVED]))
                <a href="{{ route('returns.index', ['po' => $po->number]) }}" wire:navigate class="btn-light btn-sm">Return to supplier</a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h3 class="card-title">Parts</h3></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th class="w-full">Part</th><th class="text-right">Ordered</th><th class="text-right">Received</th><th class="text-right">Unit cost</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($po->items as $i)
                        <tr wire:key="i{{ $i->id }}">
                            <td><div class="font-medium">{{ $i->part->name }}</div><div class="font-mono text-xs text-ink-400">{{ $i->part->sku }}</div></td>
                            <td class="text-right">{{ $fmt($i->qty) }} {{ $i->part->unit }}</td>
                            <td class="text-right font-semibold {{ $i->remaining() > 0 && (float) $i->received_qty > 0 ? 'text-amber-600' : ((float) $i->received_qty > 0 ? 'text-emerald-700' : 'text-ink-400') }}">{{ $fmt($i->received_qty) }}</td>
                            <td class="text-right">{{ amount($i->rate) }}</td>
                            <td class="text-right font-semibold">{{ amount($i->amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <dl class="ml-auto max-w-sm space-y-1.5 border-t border-ink-100 p-5 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Subtotal</dt><dd>{{ money($po->subtotal) }}</dd></div>
                @if ((float) $po->tax_amount) <div class="flex justify-between"><dt class="text-ink-500">Tax ({{ $fmt($po->taxRate()) }}%)</dt><dd>{{ money($po->tax_amount) }}</dd></div> @endif
                @if ((float) $po->other_charges) <div class="flex justify-between"><dt class="text-ink-500">Transport / other</dt><dd>{{ money($po->other_charges) }}</dd></div> @endif
                <div class="flex justify-between border-t border-ink-200 pt-2 text-base"><dt class="font-bold">Total</dt><dd class="font-extrabold">{{ money($po->grand_total) }}</dd></div>
                @if ((float) $po->credited) <div class="flex justify-between"><dt class="text-ink-500">Returned (debit notes)</dt><dd>− {{ money($po->credited) }}</dd></div> @endif
                <div class="flex justify-between"><dt class="text-ink-500">Paid</dt><dd>{{ money($po->paid) }}</dd></div>
                <div class="flex justify-between"><dt class="font-semibold">Balance owed</dt><dd class="font-extrabold {{ $owing ? 'text-brand-600' : '' }}">{{ money(max(0, (float) $po->balance)) }}</dd></div>
            </dl>
            @if ($po->notes) <div class="whitespace-pre-line border-t border-ink-100 px-5 py-4 text-sm text-ink-600"><p class="label">Notes</p>{{ $po->notes }}</div> @endif
            @if ($po->isDraft() || $po->status === PurchaseOrder::ORDERED)
                <div class="border-t border-ink-100 px-5 py-3 text-right"><button class="text-xs font-semibold text-ink-400 hover:text-brand-600" wire:click="$set('showCancel', true)">Cancel this purchase order</button></div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Supplier payments</h3></div>
                <ul class="divide-y divide-ink-100 text-sm">
                    @forelse ($po->payments as $p)
                        <li class="px-5 py-3" wire:key="sp{{ $p->id }}">
                            <p class="font-semibold {{ (float) $p->amount < 0 ? 'text-emerald-700' : '' }}">{{ money($p->amount) }} <span class="font-normal text-ink-500">· {{ $p->mode }}</span></p>
                            <p class="text-xs text-ink-400"><span class="font-mono">{{ $p->number }}</span> · {{ $p->date->format('d M Y') }} {{ $p->reference ? '· '.$p->reference : '' }} {{ $p->note ? '· '.$p->note : '' }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-ink-400">No payments yet.</li>
                    @endforelse
                </ul>
            </div>
            @if ($po->returns->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Returns</h3></div>
                    <ul class="divide-y divide-ink-100 text-sm">
                        @foreach ($po->returns as $r)
                            <li class="flex items-center justify-between px-5 py-3"><span class="font-mono text-xs font-bold">{{ $r->number }}</span><span>{{ money($r->total) }}</span>
                                <a href="{{ route('print.return', $r) }}" target="_blank" class="btn-icon"><x-icon name="printer" class="h-4 w-4" /></a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <x-modal show="showReceive" title="Receive goods" max-width="max-w-2xl">
        <form wire:submit="saveReceive" id="receiveForm" class="space-y-4">
            <p class="text-sm text-ink-600">Enter what actually arrived. Stock goes up now, and each part's buying price is updated to this order's cost.</p>
            <table class="table">
                <thead><tr><th>Part</th><th class="text-right">Still to come</th><th class="w-36 text-right">Received now</th></tr></thead>
                <tbody>
                @foreach ($po->items as $i)
                    <tr wire:key="r{{ $i->id }}">
                        <td>{{ $i->part->name }}</td>
                        <td class="text-right">{{ $fmt($i->remaining()) }} {{ $i->part->unit }}</td>
                        <td><input type="number" step="any" min="0" wire:model="receive.{{ $i->id }}" class="input text-right" @disabled($i->remaining() <= 0)></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div><label class="label">Supplier's invoice / delivery note no</label><input wire:model="supplierInvoiceNo" class="input font-mono"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="receiveForm" class="btn-primary" wire:loading.attr="disabled" wire:target="saveReceive">Receive into stock</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="showPay" title="Pay supplier">
        <form wire:submit="savePay" id="payForm" class="space-y-4">
            <p class="rounded-lg bg-ink-50 p-3 text-sm">{{ $po->supplier->name }} — balance owed <b>{{ money($po->balance) }}</b></p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">Amount *</label><input type="number" step="any" min="0" wire:model="payAmount" class="input font-bold">
                    @error('payAmount') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">Paid by *</label><select wire:model="payMode" class="input">@foreach ($modes as $m) <option>{{ $m }}</option> @endforeach</select></div>
            </div>
            <div><label class="label">Reference</label><input wire:model="payReference" class="input font-mono uppercase"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="payForm" class="btn-primary" wire:loading.attr="disabled" wire:target="savePay">Save payment</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="showCancel" title="Cancel purchase order">
        <form wire:submit="confirmCancel" id="cancelPoForm" class="space-y-3">
            <div><label class="label">Reason *</label><input wire:model="cancelReason" class="input"></div>
            @error('cancelReason') <p class="error">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Keep</button>
            <button type="submit" form="cancelPoForm" class="btn-danger" wire:loading.attr="disabled">Cancel order</button>
        </x-slot:footer>
    </x-modal>
</div>
