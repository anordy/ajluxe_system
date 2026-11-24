<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Outstanding (owed to you)</p><p class="mt-1 text-2xl font-extrabold {{ $outstanding > 0 ? 'text-brand-600' : 'text-ink-900' }}">{{ money($outstanding) }}</p></div>
        <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Received today</p><p class="mt-1 text-2xl font-extrabold text-ink-900">{{ money($todayIn) }}</p></div>
        <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Received this month</p><p class="mt-1 text-2xl font-extrabold text-ink-900">{{ money($monthIn) }}</p></div>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="grid w-full grid-cols-2 gap-1 rounded-lg bg-ink-100 p-1 lg:w-64">
            @foreach (['invoices' => 'Invoices', 'payments' => 'Payments'] as $key => $label)
                <button wire:click="$set('view', @js($key))" class="rounded-md py-1.5 text-sm font-semibold {{ $view === $key ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="relative w-full lg:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="{{ $view === 'payments' ? 'Receipt no, reference, customer…' : 'Invoice no, job no, reg no, customer…' }}">
        </div>
        @if ($view === 'invoices')
            <select wire:model.live="kind" class="input lg:max-w-[150px]">
                <option value="">Garage &amp; spare parts</option><option value="garage">Garage</option><option value="counter">Spare parts sales</option>
            </select>
            <select wire:model.live="status" class="input lg:max-w-[160px]">
                <option value="">All invoices</option><option value="unpaid">Unpaid / part paid</option><option value="paid">Paid</option><option value="cancelled">Cancelled</option>
            </select>
        @endif
        <div class="flex items-center gap-2">
            <input type="date" wire:model.live="from" class="input" title="From">
            <span class="text-ink-400">–</span>
            <input type="date" wire:model.live="to" class="input" title="To">
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            @if ($view === 'invoices')
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Job / vehicle</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($rows as $inv)
                        <tr wire:key="i{{ $inv->id }}">
                            <td><a href="{{ route('invoices.show', $inv) }}" wire:navigate class="font-mono text-xs font-bold text-ink-900 hover:text-brand-600">{{ $inv->number }}</a><div class="text-xs text-ink-400">{{ $inv->date->format('d M Y') }}</div></td>
                            <td><div class="font-medium">{{ $inv->customerName() }}</div><div class="text-xs text-ink-400">{{ $inv->customer?->phone }}</div></td>
                            <td>
                                @if ($inv->jobCard)
                                    <a href="{{ route('job-cards.show', $inv->jobCard) }}" wire:navigate class="font-mono text-xs font-semibold text-ink-700 hover:text-brand-600">{{ $inv->jobCard->number }}</a>
                                    <div class="font-mono text-xs text-ink-400">{{ $inv->jobCard->vehicle?->reg_no }}</div>
                                @else <span class="badge-gray">Spare parts sale</span> @endif
                                @if ((float) $inv->credited) <div class="text-[11px] text-ink-400">returns − {{ amount($inv->credited) }}</div> @endif
                            </td>
                            <td class="text-right font-semibold">{{ amount($inv->grand_total) }}</td>
                            <td class="text-right">{{ amount($inv->paid) }}</td>
                            <td class="text-right font-bold {{ (float) $inv->balance > 0 && $inv->status !== 'cancelled' ? 'text-brand-600' : 'text-ink-400' }}">{{ amount($inv->balance) }}</td>
                            <td><span class="{{ \App\Models\Invoice::STATUS_BADGES[$inv->status] }}">{{ \App\Models\Invoice::STATUS_LABELS[$inv->status] }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                @if ((float) $inv->balance > 0 && $inv->status !== 'cancelled')
                                    <button class="btn-primary btn-sm" wire:click="$dispatch('open-payment-form', { invoiceId: {{ $inv->id }} })">Pay</button>
                                @endif
                                <a href="{{ route('invoices.show', $inv) }}" wire:navigate class="btn-icon" title="View" aria-label="View {{ $inv->number }}"><x-icon name="eye" class="h-4 w-4" /></a>
                                <a href="{{ route('print.invoice', $inv) }}" target="_blank" class="btn-icon" title="Print"><x-icon name="printer" class="h-4 w-4" /></a>
                                @if ($inv->status !== 'cancelled' && $this->canVoid())
                                    <div x-data="{ m: false }" class="relative inline-block">
                                        <button class="btn-icon" @click="m = !m" @click.outside="m = false" title="More">⋯</button>
                                        <div x-show="m" x-cloak class="absolute right-0 z-10 mt-1 w-44 overflow-hidden rounded-lg border border-ink-100 bg-white text-left text-sm shadow-lg">
                                            @if ($inv->isCounter()) <a href="{{ route('print.thermal', $inv) }}" target="_blank" class="block px-3 py-2 hover:bg-ink-50">80mm receipt</a> @endif
                                            <a href="{{ route('returns.index', ['invoice' => $inv->number]) }}" wire:navigate class="block px-3 py-2 hover:bg-ink-50">Return items</a>
                                            @if ($inv->isCounter()) <button wire:click="openVoid({{ $inv->id }})" class="block w-full px-3 py-2 text-left text-brand-600 hover:bg-brand-50">Void sale</button> @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center text-ink-400">No invoices found. Invoices are generated from job cards.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @else
                <table class="table">
                    <thead><tr><th>Receipt</th><th>Customer</th><th>For</th><th>Paid by</th><th class="text-right">Amount</th><th>Received by</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($rows as $p)
                        <tr wire:key="p{{ $p->id }}">
                            <td><div class="font-mono text-xs font-bold text-ink-900">{{ $p->number }}</div><div class="text-xs text-ink-400">{{ $p->date->format('d M Y') }}</div></td>
                            <td class="font-medium">{{ $p->customer?->name ?? '—' }}</td>
                            <td class="font-mono text-xs">@if ($p->invoice) <a href="{{ route('invoices.show', $p->invoice) }}" wire:navigate class="hover:text-brand-600">{{ $p->invoice->number }}</a> @else {{ $p->jobCard ? 'Advance · '.$p->jobCard->number : '—' }} @endif</td>
                            <td>{{ $p->mode }} @if ($p->reference) <div class="font-mono text-xs text-ink-400">{{ $p->reference }}</div> @endif</td>
                            <td class="text-right font-semibold">{{ amount($p->amount) }}</td>
                            <td class="text-ink-500">{{ $p->creator?->name ?? '—' }}</td>
                            <td class="text-right"><a href="{{ route('print.receipt', $p) }}" target="_blank" class="btn-icon" title="Print receipt"><x-icon name="printer" class="h-4 w-4" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center text-ink-400">No payments found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @endif
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-100 px-4 py-3 text-sm">
            <span class="text-ink-500">{{ $view === 'payments' ? 'Total received' : 'Total invoiced' }} (filtered): <b class="text-ink-900">{{ money($total) }}</b></span>
            @if ($rows->hasPages()) {{ $rows->links() }} @endif
        </div>
    </div>

    <livewire:payments.record-form />

    <x-modal show="showVoid" title="Void spare parts sale">
        <form wire:submit="confirmVoid" id="voidForm" class="space-y-4">
            @if ($voidInvoice)
                <p class="rounded-lg bg-ink-50 p-3 text-sm"><span class="font-mono font-bold">{{ $voidInvoice->number }}</span> · {{ $voidInvoice->customerName() }} · {{ money($voidInvoice->grand_total) }}
                    @if ((float) $voidInvoice->paid > 0) <br>The customer paid <b>{{ money($voidInvoice->paid) }}</b> — this will be recorded as refunded. @endif</p>
            @endif
            <p class="text-sm text-ink-600">All parts go back to stock and the sale is marked cancelled. This cannot be undone.</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2"><label class="label">Reason *</label><input wire:model="voidReason" class="input" placeholder="e.g. Entered by mistake"></div>
                <div><label class="label">Refund by</label><select wire:model="voidRefundMode" class="input">@foreach ($modes as $m) <option>{{ $m }}</option> @endforeach</select></div>
            </div>
            @error('voidReason') <p class="error">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Keep sale</button>
            <button type="submit" form="voidForm" class="btn-danger" wire:loading.attr="disabled" wire:target="confirmVoid">Void sale</button>
        </x-slot:footer>
    </x-modal>
</div>
