@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($canCost)
            <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Stock value (at cost)</p><p class="mt-1 text-2xl font-extrabold">{{ money($stockValue) }}</p></div>
        @endif
        <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Stock value (selling)</p><p class="mt-1 text-2xl font-extrabold">{{ money($retailValue) }}</p></div>
        <button wire:click="$set('filter', 'low'); $set('view', 'levels')" class="card p-5 text-left hover:ring-2 hover:ring-amber-300"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Low stock</p><p class="mt-1 text-2xl font-extrabold {{ $lowCount ? 'text-amber-600' : '' }}">{{ $lowCount }}</p></button>
        <button wire:click="$set('filter', 'out'); $set('view', 'levels')" class="card p-5 text-left hover:ring-2 hover:ring-brand-200"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Out of stock</p><p class="mt-1 text-2xl font-extrabold {{ $outCount ? 'text-brand-600' : '' }}">{{ $outCount }}</p></button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="grid w-full shrink-0 grid-cols-2 gap-1 rounded-lg bg-ink-100 p-1 lg:w-64">
            @foreach (['levels' => 'Stock levels', 'movements' => 'Movements'] as $k => $label)
                <button wire:click="$set('view', @js($k))" class="whitespace-nowrap rounded-md py-1.5 text-sm font-semibold {{ $view === $k ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="relative w-full lg:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="{{ $view === 'movements' ? 'Part or document no…' : 'Part name, SKU, OEM no…' }}">
        </div>
        <select wire:model.live="filter" class="input lg:max-w-[190px]">
            @if ($view === 'movements')
                <option value="">All movements</option>
                @foreach ($types as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
            @else
                <option value="">All parts</option><option value="low">Low stock</option><option value="out">Out of stock</option>
            @endif
        </select>
        @if ($view === 'movements')
            <div class="flex items-center gap-2"><input type="date" wire:model.live="from" class="input lg:w-40"><span class="text-ink-400">–</span><input type="date" wire:model.live="to" class="input lg:w-40"></div>
        @endif
        @if ($canAdjust = $this->canAdjust())
            <button class="btn-primary shrink-0 whitespace-nowrap lg:ml-auto" wire:click="openAdjust"><x-icon name="pencil" class="h-4 w-4" /> Adjust Stock</button>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            @if ($view === 'movements')
                <table class="table">
                    <thead><tr><th>Date</th><th>Part</th><th>Movement</th><th>Document</th><th class="text-right">In</th><th class="text-right">Out</th><th class="text-right">Balance</th><th>By</th></tr></thead>
                    <tbody>
                    @forelse ($rows as $m)
                        <tr wire:key="m{{ $m->id }}">
                            <td class="whitespace-nowrap text-xs text-ink-500">{{ $m->date->format('d M Y H:i') }}</td>
                            <td><div class="font-medium">{{ $m->part?->name }}</div><div class="font-mono text-xs text-ink-400">{{ $m->part?->sku }}</div></td>
                            <td>{{ $types[$m->type] ?? $m->type }} @if ($m->note) <div class="text-xs text-ink-400">{{ $m->note }}</div> @endif</td>
                            <td class="font-mono text-xs">{{ $m->reference_no ?? '—' }}</td>
                            <td class="text-right font-semibold text-emerald-700">{{ (float) $m->qty_in ? '+'.$fmt($m->qty_in) : '' }}</td>
                            <td class="text-right font-semibold text-brand-600">{{ (float) $m->qty_out ? '−'.$fmt($m->qty_out) : '' }}</td>
                            <td class="text-right font-bold">{{ $fmt($m->balance_after) }}</td>
                            <td class="text-xs text-ink-500">{{ $m->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center text-ink-400">No stock movements found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @else
                <table class="table">
                    <thead><tr><th>Part</th><th>Location</th><th class="text-right">In stock</th><th class="text-right">Re-order at</th>
                        @if ($canCost) <th class="text-right">Cost</th><th class="text-right">Value</th> @endif
                        <th class="text-right">Selling</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($rows as $p)
                        <tr wire:key="p{{ $p->id }}" class="{{ $p->is_active ? '' : 'opacity-50' }}">
                            <td><div class="font-medium">{{ $p->name }}</div><div class="font-mono text-xs text-ink-400">{{ $p->sku }} {{ $p->brand ? '· '.$p->brand : '' }}</div></td>
                            <td class="text-ink-500">{{ $p->rack_location ?: '—' }}</td>
                            <td class="text-right font-bold {{ (float) $p->stock_qty <= 0 ? 'text-brand-600' : ($p->isLow() ? 'text-amber-600' : '') }}">{{ $fmt($p->stock_qty) }} <span class="font-normal text-ink-400">{{ $p->unit }}</span></td>
                            <td class="text-right text-ink-500">{{ (float) $p->reorder_level ? $fmt($p->reorder_level) : '—' }}</td>
                            @if ($canCost)
                                <td class="text-right">{{ amount($p->purchase_price) }}</td>
                                <td class="text-right font-semibold">{{ amount(max(0, (float) $p->stock_qty) * (float) $p->purchase_price) }}</td>
                            @endif
                            <td class="text-right">{{ amount($p->selling_price) }}</td>
                            <td class="text-right">@if ($canAdjust) <button class="btn-icon" title="Adjust" wire:click="openAdjust({{ $p->id }})"><x-icon name="pencil" class="h-4 w-4" /></button> @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center text-ink-400">No parts found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @endif
        </div>
        @if ($rows->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $rows->links() }}</div> @endif
    </div>

    <x-modal show="showAdjust" title="Adjust stock">
        <form wire:submit="saveAdjust" id="adjustForm" class="space-y-4">
            <div>
                <label class="label">Part *</label>
                @if ($adjPart)
                    <div class="flex items-center justify-between rounded-lg bg-ink-50 px-3 py-2 text-sm">
                        <span><b>{{ $adjPart->name }}</b> <span class="font-mono text-xs text-ink-400">{{ $adjPart->sku }}</span><br>
                            <span class="text-ink-500">Now in stock: <b class="text-ink-900">{{ $fmt($adjPart->stock_qty) }} {{ $adjPart->unit }}</b></span></span>
                        <button type="button" class="text-xs font-semibold text-brand-600" wire:click="$set('partId', null)">Change</button>
                    </div>
                @else
                    <div class="relative">
                        <input wire:model.live.debounce.250ms="partSearch" class="input" placeholder="Search part…" autocomplete="off">
                        @if ($partMatches->isNotEmpty())
                            <div class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-ink-100 bg-white shadow-lg">
                                @foreach ($partMatches as $pm)
                                    <button type="button" wire:click="pickPart({{ $pm->id }})" class="flex w-full justify-between px-3 py-2 text-left text-sm hover:bg-ink-50"><span>{{ $pm->name }}</span><span class="text-xs text-ink-400">{{ $fmt($pm->stock_qty) }} {{ $pm->unit }}</span></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                @error('partId') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-3 gap-1 rounded-lg bg-ink-100 p-1">
                @foreach (['count' => 'Set counted qty', 'add' => 'Add', 'remove' => 'Remove'] as $k => $label)
                    <button type="button" wire:click="$set('adjustMode', @js($k))" class="rounded-md py-1.5 text-xs font-semibold {{ $adjustMode === $k ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div>
                <label class="label">{{ $adjustMode === 'count' ? 'Quantity counted on the shelf' : 'Quantity' }} *</label>
                <input type="number" step="any" min="0" wire:model="adjustQty" class="input">
                @error('adjustQty') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Reason *</label>
                <input wire:model="adjustReason" class="input" placeholder="e.g. Stock count, damaged, found in store">
                @error('adjustReason') <p class="error">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="adjustForm" class="btn-primary" wire:loading.attr="disabled" wire:target="saveAdjust">Save adjustment</button>
        </x-slot:footer>
    </x-modal>
</div>
