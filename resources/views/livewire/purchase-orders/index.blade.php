<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Owed to suppliers</p><p class="mt-1 text-2xl font-extrabold {{ $owed > 0 ? 'text-brand-600' : '' }}">{{ money($owed) }}</p></div>
        <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Orders awaiting delivery</p><p class="mt-1 text-2xl font-extrabold">{{ $openCount }}</p></div>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="PO no, supplier, supplier invoice…">
        </div>
        <select wire:model.live="status" class="input lg:max-w-[180px]">
            <option value="open">Open orders</option><option value="unpaid">Unpaid</option><option value="">All</option>
            @foreach (\App\Models\PurchaseOrder::LABELS as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
        </select>
        <select wire:model.live="supplier" class="input lg:max-w-[220px]">
            <option value="">All suppliers</option>
            @foreach ($suppliers as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
        </select>
        <a href="{{ route('purchase-orders.create') }}" wire:navigate class="btn-primary lg:ml-auto"><x-icon name="plus" class="h-4 w-4" /> New Purchase Order</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>PO</th><th>Supplier</th><th class="text-right">Items</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($rows as $po)
                    <tr wire:key="po{{ $po->id }}" class="cursor-pointer" onclick="Livewire.navigate('{{ route('purchase-orders.show', $po) }}')">
                        <td><div class="font-mono text-xs font-bold text-ink-900">{{ $po->number }}</div><div class="text-xs text-ink-400">{{ $po->date->format('d M Y') }}</div></td>
                        <td class="font-medium">{{ $po->supplier?->name }} @if ($po->supplier_invoice_no) <div class="font-mono text-xs text-ink-400">Inv {{ $po->supplier_invoice_no }}</div> @endif</td>
                        <td class="text-right">{{ $po->items_count }}</td>
                        <td class="text-right font-semibold">{{ amount($po->grand_total) }}</td>
                        <td class="text-right">{{ amount($po->paid) }}</td>
                        <td class="text-right font-bold {{ (float) $po->balance > 0 && in_array($po->status, \App\Models\PurchaseOrder::OWING) ? 'text-brand-600' : 'text-ink-400' }}">{{ amount($po->balance) }}</td>
                        <td><span class="{{ $po->badge() }}">{{ $po->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-ink-400">No purchase orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $rows->links() }}</div> @endif
    </div>
</div>
