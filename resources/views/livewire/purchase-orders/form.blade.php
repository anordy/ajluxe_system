<form wire:submit="save" class="mx-auto max-w-5xl space-y-6">
    <a href="{{ $po ? route('purchase-orders.show', $po) : route('purchase-orders.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-ink-500 hover:text-ink-900">← {{ $po ? $po->number : 'Purchase orders' }}</a>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Order details</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="label">Supplier *</label>
                <select wire:model="supplier_id" class="input">
                    <option value="">— Choose supplier —</option>
                    @foreach ($suppliers as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                </select>
                @error('supplier_id') <p class="error">{{ $message }}</p> @enderror
                @if ($suppliers->isEmpty()) <p class="hint">No suppliers yet — <a href="{{ route('suppliers.index') }}" wire:navigate class="font-semibold text-brand-600">add one</a>.</p> @endif
            </div>
            <div><label class="label">Order date *</label><input type="date" wire:model="date" class="input"></div>
            <div><label class="label">Expected</label><input type="date" wire:model="expected_date" class="input"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Parts</h2>
            <button type="button" class="btn-light btn-sm" wire:click="addLowStock">+ Add all low-stock parts</button>
        </div>
        <div class="border-b border-ink-100 p-4">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
                <input wire:model.live.debounce.250ms="partSearch" class="input pl-9" placeholder="Search a part to add…" autocomplete="off">
                @if ($matches->isNotEmpty())
                    <div class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-ink-100 bg-white shadow-lg">
                        @foreach ($matches as $m)
                            <button type="button" wire:click="addPart({{ $m->id }})" class="flex w-full justify-between px-4 py-2.5 text-left text-sm hover:bg-ink-50">
                                <span><b class="font-medium">{{ $m->name }}</b> <span class="font-mono text-xs text-ink-400">{{ $m->sku }}</span></span>
                                <span class="text-xs text-ink-500">stock {{ rtrim(rtrim(number_format($m->stock_qty, 2), '0'), '.') }} · last cost {{ amount($m->purchase_price) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Part</th><th class="text-right">In stock</th><th class="w-32 text-right">Qty</th><th class="w-40 text-right">Unit cost</th><th class="text-right">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse ($lines as $i => $l)
                    <tr wire:key="l{{ $l['part_id'] }}">
                        <td><div class="font-medium">{{ $l['name'] }}</div><div class="font-mono text-xs text-ink-400">{{ $l['sku'] }}</div></td>
                        <td class="text-right text-ink-500">{{ rtrim(rtrim(number_format($l['stock'], 2), '0'), '.') }} {{ $l['unit'] }}</td>
                        <td><input type="number" step="any" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.qty" class="input text-right">
                            @error("lines.$i.qty") <p class="error">{{ $message }}</p> @enderror</td>
                        <td><input type="number" step="any" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.rate" class="input text-right"></td>
                        <td class="text-right font-semibold">{{ amount((float) $l['qty'] * (float) $l['rate']) }}</td>
                        <td><button type="button" class="btn-icon hover:text-brand-600" wire:click="removeLine({{ $i }})"><x-icon name="trash" class="h-4 w-4" /></button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-ink-400">Search above to add parts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="grid gap-6 border-t border-ink-100 p-5 md:grid-cols-2">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Tax / VAT %</label><input type="number" step="any" min="0" max="100" wire:model.live.debounce.400ms="tax_rate" class="input"></div>
                <div><label class="label">Transport / other</label><input type="number" step="any" min="0" wire:model.live.debounce.400ms="other_charges" class="input"></div>
                <div class="col-span-2"><label class="label">Notes</label><textarea wire:model="notes" rows="2" class="input"></textarea></div>
            </div>
            <dl class="space-y-1.5 self-end text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Subtotal</dt><dd>{{ money($subtotal) }}</dd></div>
                @if ($tax > 0) <div class="flex justify-between"><dt class="text-ink-500">Tax</dt><dd>{{ money($tax) }}</dd></div> @endif
                @if ((float) $other_charges > 0) <div class="flex justify-between"><dt class="text-ink-500">Transport / other</dt><dd>{{ money($other_charges) }}</dd></div> @endif
                <div class="flex justify-between border-t border-ink-200 pt-2 text-base"><dt class="font-bold">Total</dt><dd class="font-extrabold">{{ money($total) }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <button type="submit" class="btn-light" wire:loading.attr="disabled" @disabled(! $lines)>Save draft</button>
        <button type="button" class="btn-primary" wire:click="save(true)" wire:loading.attr="disabled" @disabled(! $lines)>Save &amp; mark ordered</button>
    </div>
</form>
