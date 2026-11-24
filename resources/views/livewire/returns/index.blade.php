@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Note no, invoice / PO no, name…">
        </div>
        <select wire:model.live="type" class="input lg:max-w-[200px]">
            <option value="">All returns</option>
            @foreach (\App\Models\StockReturn::TYPES as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
        </select>
        <div class="flex gap-2 lg:ml-auto">
            <button class="btn-primary" wire:click="open('customer')"><x-icon name="return" class="h-4 w-4" /> Customer return</button>
            <button class="btn-dark" wire:click="open('supplier')"><x-icon name="truck" class="h-4 w-4" /> Return to supplier</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Note</th><th>Type</th><th>From / to</th><th>Document</th><th>Reason</th><th class="text-right">Value</th><th class="text-right">Refund</th><th></th></tr></thead>
                <tbody>
                @forelse ($list as $r)
                    <tr wire:key="r{{ $r->id }}">
                        <td><div class="font-mono text-xs font-bold text-ink-900">{{ $r->number }}</div><div class="text-xs text-ink-400">{{ $r->date->format('d M Y') }}</div></td>
                        <td><span class="{{ $r->isCustomer() ? 'badge-gray' : 'badge-dark' }}">{{ $r->isCustomer() ? 'Customer' : 'Supplier' }}</span></td>
                        <td class="font-medium">{{ $r->partyName() }}</td>
                        <td class="font-mono text-xs">
                            @if ($r->purchaseOrder) <a href="{{ route('purchase-orders.show', $r->purchaseOrder) }}" wire:navigate class="hover:text-brand-600">{{ $r->purchaseOrder->number }}</a>
                            @else {{ $r->invoice?->number }} @endif
                        </td>
                        <td class="max-w-[220px] truncate text-ink-500" title="{{ $r->reason }}">{{ $r->reason }}</td>
                        <td class="text-right font-semibold">{{ amount($r->total) }}</td>
                        <td class="text-right">{{ (float) $r->refund_amount ? amount($r->refund_amount).' · '.$r->refund_mode : '—' }}</td>
                        <td class="text-right"><a href="{{ route('print.return', $r) }}" target="_blank" class="btn-icon" title="Print"><x-icon name="printer" class="h-4 w-4" /></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-ink-400">No returns recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($list->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $list->links() }}</div> @endif
    </div>

    <x-modal show="showForm" :title="$formType === 'customer' ? 'Customer return (credit note)' : 'Return to supplier (debit note)'" max-width="max-w-2xl">
        <form wire:submit="save" id="returnForm" class="space-y-4">
            <div>
                <label class="label">{{ $formType === 'customer' ? 'Invoice number' : 'Purchase order number' }} *</label>
                <div class="flex gap-2">
                    <input wire:model="docNo" wire:keydown.enter.prevent="findDoc" class="input font-mono uppercase" placeholder="{{ $formType === 'customer' ? 'INV-'.now()->year.'-0001' : 'PO-'.now()->year.'-0001' }}">
                    <button type="button" class="btn-light" wire:click="findDoc">Find</button>
                </div>
                @error('docNo') <p class="error">{{ $message }}</p> @enderror
                @error('docId') <p class="error">{{ $message }}</p> @enderror
            </div>

            @if ($doc)
                <div class="rounded-lg bg-ink-50 p-3 text-sm">
                    @if ($formType === 'customer')
                        <b>{{ $doc->customerName() }}</b> · {{ $doc->date->format('d M Y') }} · total {{ money($doc->grand_total) }} · paid {{ money($doc->paid) }}
                        @if ((float) $doc->credited) · already credited {{ money($doc->credited) }} @endif
                    @else
                        <b>{{ $doc->supplier->name }}</b> · total {{ money($doc->grand_total) }} · paid {{ money($doc->paid) }}
                    @endif
                </div>

                <table class="table">
                    <thead><tr><th>Part</th><th class="text-right">{{ $formType === 'customer' ? 'Sold' : 'Received' }}</th><th class="text-right">Already returned</th><th class="text-right">Price</th><th class="w-32 text-right">Return now</th></tr></thead>
                    <tbody>
                    @forelse ($rows as $partId => $row)
                        <tr wire:key="rr{{ $partId }}">
                            <td>{{ $row->description }} @if ($formType === 'supplier') <div class="text-xs text-ink-400">in stock {{ $fmt($row->in_stock) }}</div> @endif</td>
                            <td class="text-right">{{ $fmt($formType === 'customer' ? $row->sold : $row->received) }}</td>
                            <td class="text-right text-ink-500">{{ $fmt($row->returned) }}</td>
                            <td class="text-right">{{ amount($row->rate) }}</td>
                            <td><input type="number" step="any" min="0" max="{{ $row->returnable }}" wire:model="qty.{{ $partId }}" class="input text-right" placeholder="0" @disabled($row->returnable <= 0)></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-ink-400">No parts on this document can be returned (labour cannot be returned).</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @error('qty') <p class="error">{{ $message }}</p> @enderror

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2"><label class="label">Reason *</label><input wire:model="reason" class="input" placeholder="e.g. Wrong part, faulty, not needed">
                        @error('reason') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">{{ $formType === 'customer' ? 'Refund by (if paid)' : 'Refund by supplier' }}</label>
                        <select wire:model="refundMode" class="input">@foreach ($modes as $m) <option>{{ $m }}</option> @endforeach</select></div>
                </div>
                <p class="hint">
                    @if ($formType === 'customer') Parts go back into stock. The value is taken off what the customer owes; if they already paid more, the difference is recorded as a refund.
                    @else Parts leave stock. The value is taken off what you owe the supplier; if you already paid more, the supplier owes you a refund. @endif
                </p>
            @endif
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="returnForm" class="btn-primary" wire:loading.attr="disabled" wire:target="save" @disabled(! $doc)>Save return</button>
        </x-slot:footer>
    </x-modal>
</div>
