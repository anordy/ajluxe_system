@php
    use App\Models\JobCard;
    $workflow = [JobCard::OPEN, JobCard::IN_PROGRESS, JobCard::WAITING_PARTS, JobCard::QUALITY_CHECK, JobCard::READY, JobCard::DELIVERED];
    $closed = $job->isClosed();
    $hasItems = $job->items->isNotEmpty();
    $balanceDue = $invoice ? (float) $invoice->balance : max(0, (float) $job->grand_total - $paid);
@endphp
<div class="space-y-6">
    <a href="{{ route('job-cards.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-ink-500 hover:text-ink-900">← Job cards</a>

    {{-- ===== header ===== --}}
    <div class="card p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-mono text-xl font-extrabold text-ink-900">{{ $job->number }}</h2>
                    <span class="badge {{ $job->badge() }}">{{ $job->status }}</span>
                    @if (in_array($job->priority, ['High', 'Urgent'])) <span class="badge-red">{{ $job->priority }}</span> @endif
                    @if ($job->isOverdue()) <span class="badge-red">Overdue</span> @endif
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                    <span class="rounded-md border-2 border-ink-900 bg-amber-300 px-2.5 py-0.5 font-mono text-sm font-bold tracking-wider text-ink-900">{{ $job->vehicle->reg_no }}</span>
                    <span class="font-medium text-ink-700">{{ trim($job->vehicle->make.' '.$job->vehicle->model) }} {{ $job->vehicle->year ? '· '.$job->vehicle->year : '' }}</span>
                    @if ($canPrice)
                        <a href="{{ route('customers.show', $job->customer) }}" wire:navigate class="font-semibold text-ink-900 hover:text-brand-600">{{ $job->customer->name }}</a>
                    @else
                        <span class="font-semibold text-ink-900">{{ $job->customer->name }}</span>
                    @endif
                    <span class="text-ink-500">{{ $job->customer->phone }}</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('print.job-card', $job) }}" target="_blank" class="btn-light btn-sm"><x-icon name="printer" class="h-4 w-4" /> Job Card</a>
                @if ($canPrice && $hasItems && $job->status !== JobCard::CANCELLED)
                    <a href="{{ route('print.proforma', $job) }}" target="_blank" class="btn-light btn-sm"><x-icon name="printer" class="h-4 w-4" /> Proforma</a>
                @endif
                @if ($invoice)
                    <a href="{{ route('print.invoice', $invoice) }}" target="_blank" class="btn-dark btn-sm"><x-icon name="printer" class="h-4 w-4" /> Invoice {{ $invoice->number }}</a>
                    @if (auth()->user()->allows('sales.view'))
                        <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="btn-light btn-sm"><x-icon name="eye" class="h-4 w-4" /> View invoice</a>
                    @endif
                @endif
            </div>
        </div>

        {{-- workflow --}}
        @if ($job->status !== JobCard::CANCELLED)
            @php $current = array_search($job->status, $workflow, true); @endphp
            <div class="mt-6 grid grid-cols-3 gap-1.5 sm:grid-cols-6">
                @foreach ($workflow as $i => $step)
                    @php
                        $done = $i < $current; $here = $i === $current;
                        $blocked = in_array($step, [JobCard::READY, JobCard::DELIVERED]) && ! $hasItems
                                || $step === JobCard::DELIVERED && ! $invoice
                                || $step === JobCard::DELIVERED && ! $canPrice;
                        $allowed = ! $closed && ! $here && ! $blocked && ($canPrice || $isMechanic);
                    @endphp
                    <button type="button" wire:key="st{{ $i }}"
                            @if ($allowed) wire:click="setStatus(@js($step))" wire:loading.attr="disabled" @if ($step === JobCard::DELIVERED) wire:confirm="Mark the vehicle as delivered? The job card will be closed." @endif @else disabled @endif
                            title="{{ $blocked && ! $here ? ($step === JobCard::DELIVERED && ! $invoice ? 'Generate the invoice first' : 'Add items first') : '' }}"
                            class="rounded-lg px-2 py-2 text-[11px] font-bold uppercase tracking-wide transition
                                {{ $here ? JobCard::SOLID[$step].' shadow-sm' : ($done ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-white text-ink-500 ring-1 ring-ink-100') }}
                                {{ $allowed ? 'cursor-pointer hover:ring-2 hover:ring-ink-300' : 'cursor-default' }} {{ ! $allowed && ! $here && ! $done ? 'opacity-60' : '' }}">
                        @if ($done) ✓ @elseif (! $here) <span class="mr-1 inline-block h-2 w-2 rounded-full {{ JobCard::DOTS[$step] }}"></span> @endif {{ $step }}
                    </button>
                @endforeach
            </div>
            @if (! $closed && $canPrice)
                <div class="mt-3 text-right">
                    <button class="text-xs font-semibold text-ink-400 hover:text-brand-600" wire:click="$set('showCancel', true)">Cancel this job card</button>
                </div>
            @endif
        @else
            <div class="mt-5 rounded-lg bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700">This job card was cancelled. Parts used on it were returned to stock.</div>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- ===== items ===== --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Services &amp; Parts</h3>
                    @if ($canPrice && $editable)
                        <div class="flex gap-2">
                            <button class="btn-dark btn-sm" wire:click="openAdd('service')"><x-icon name="plus" class="h-4 w-4" /> Labour</button>
                            <button class="btn-dark btn-sm" wire:click="openAdd('part')"><x-icon name="plus" class="h-4 w-4" /> Parts</button>
                            <button class="btn-light btn-sm" wire:click="openAdd('other')"><x-icon name="plus" class="h-4 w-4" /> Other</button>
                            @if ($pending) <button class="btn-primary btn-sm" wire:click="openAdd(@js($itemType))">{{ count($pending) }} waiting — review &amp; save</button> @endif
                        </div>
                    @elseif ($invoice)
                        <span class="badge-gray">Locked — invoiced</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr>
                            <th class="w-full">Description</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Amount</th>
                            @if ($canPrice) <th class="text-right">Cost</th> @endif
                            @if ($canPrice && $editable) <th></th> @endif
                        </tr></thead>
                        <tbody>
                        @forelse ($job->items as $item)
                            <tr wire:key="it{{ $item->id }}">
                                <td>
                                    <span class="mr-2 inline-block w-12 text-[10px] font-bold uppercase tracking-wide text-ink-400">{{ \App\Models\JobCardItem::TYPES[$item->type] }}</span>
                                    <span class="font-medium text-ink-900">{{ $item->description }}</span>
                                </td>
                                <td class="whitespace-nowrap text-right">{{ $item->qtyLabel() }}</td>
                                <td class="whitespace-nowrap text-right">{{ amount($item->rate) }}</td>
                                <td class="whitespace-nowrap text-right font-semibold">{{ amount($item->amount) }}</td>
                                @if ($canPrice) <td class="whitespace-nowrap text-right text-ink-400">{{ (float) $item->cost ? amount($item->cost) : '—' }}</td> @endif
                                @if ($canPrice && $editable)
                                    <td class="whitespace-nowrap text-right">
                                        <button class="btn-icon" wire:click="editItem({{ $item->id }})" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></button>
                                        <button class="btn-icon hover:text-brand-600" wire:click="removeItem({{ $item->id }})" wire:confirm="Remove “{{ $item->description }}”?{{ $item->type === 'part' ? ' The part goes back to stock.' : '' }}" title="Remove"><x-icon name="trash" class="h-4 w-4" /></button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-10 text-center text-ink-400">No services or parts yet.{{ $canPrice && $editable ? ' Use the buttons above to add labour and parts.' : '' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- totals --}}
                <div class="grid gap-6 border-t border-ink-100 p-5 md:grid-cols-2">
                    <div>
                        @if ($canPrice && $editable && $hasItems)
                            <form wire:submit="saveTotals" class="flex items-end gap-2">
                                <div class="flex-1"><label class="label">Discount %</label><input type="number" step="any" min="0" max="100" wire:model="discount" class="input"></div>
                                <div class="w-24"><label class="label">Tax %</label><input type="number" step="any" min="0" max="100" wire:model="taxRate" class="input"></div>
                                <button class="btn-light" wire:loading.attr="disabled" wire:target="saveTotals">Apply</button>
                            </form>
                            @error('discount') <p class="error">{{ $message }}</p> @enderror
                            @error('taxRate') <p class="error">{{ $message }}</p> @enderror
                        @endif
                        @if ($canPrice && $hasItems)
                            @php $profit = $job->profit(); @endphp
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg bg-ink-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase text-ink-400">Cost</p><p class="font-bold">{{ money($job->totalCost()) }}</p></div>
                                <div class="rounded-lg {{ $profit < 0 ? 'bg-brand-50' : 'bg-emerald-50' }} px-3 py-2"><p class="text-[11px] font-semibold uppercase {{ $profit < 0 ? 'text-brand-600' : 'text-emerald-700' }}">Profit</p><p class="font-bold">{{ money($profit) }}</p></div>
                            </div>
                            <p class="hint mt-2">Cost and profit are only visible to admins and managers and never printed.</p>
                        @endif
                    </div>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-500">Subtotal</dt><dd class="font-medium">{{ money($job->subtotal) }}</dd></div>
                        @if ((float) $job->discount) <div class="flex justify-between"><dt class="text-ink-500">Discount ({{ rtrim(rtrim(number_format($job->discount_rate, 2), '0'), '.') }}%)</dt><dd class="font-medium">− {{ money($job->discount) }}</dd></div> @endif
                        @if ((float) $job->tax_amount) <div class="flex justify-between"><dt class="text-ink-500">Tax ({{ (float) $job->tax_rate }}%)</dt><dd class="font-medium">{{ money($job->tax_amount) }}</dd></div> @endif
                        <div class="flex justify-between border-t border-ink-200 pt-2 text-base"><dt class="font-bold">Grand total</dt><dd class="font-extrabold">{{ money($job->grand_total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Paid</dt><dd class="font-medium">{{ money($paid) }}</dd></div>
                        <div class="flex justify-between"><dt class="font-semibold">Balance due</dt><dd class="font-extrabold {{ $balanceDue > 0 ? 'text-brand-600' : 'text-emerald-700' }}">{{ money($balanceDue) }}</dd></div>
                    </dl>
                </div>

                @if ($canPrice && $job->status !== JobCard::CANCELLED)
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-ink-100 bg-ink-50 px-5 py-3.5">
                        @if (! $invoice)
                            @unless ($hasItems) <span class="mr-auto text-xs text-ink-400">Add at least one service or part to generate the invoice.</span> @endunless
                            <button class="btn-light" wire:click="$dispatch('open-payment-form', { jobCardId: {{ $job->id }} })" @disabled($closed)>Record Advance</button>
                            <button class="btn-primary" wire:click="generateInvoice" wire:loading.attr="disabled" wire:target="generateInvoice" @disabled(! $hasItems)
                                    wire:confirm="Generate the invoice now? Services and parts will be locked.">
                                <span wire:loading.remove wire:target="generateInvoice">Generate Invoice</span><span wire:loading wire:target="generateInvoice">Generating…</span>
                            </button>
                        @else
                            <span class="mr-auto text-sm">Invoice <span class="font-mono font-bold">{{ $invoice->number }}</span>
                                <span class="{{ \App\Models\Invoice::STATUS_BADGES[$invoice->status] }} ml-1">{{ \App\Models\Invoice::STATUS_LABELS[$invoice->status] }}</span></span>
                            @if ((float) $invoice->balance > 0)
                                <button class="btn-primary" wire:click="$dispatch('open-payment-form', { invoiceId: {{ $invoice->id }} })">Record Payment</button>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            {{-- ===== work notes ===== --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">Mechanic's Work Notes</h3></div>
                <form wire:submit="saveWorkNotes" class="card-body space-y-3">
                    <textarea wire:model="workNotes" rows="4" class="input" placeholder="What was found and what was done…" @disabled($job->status === JobCard::CANCELLED)></textarea>
                    @if ($job->status !== JobCard::CANCELLED)
                        <div class="text-right"><button class="btn-light btn-sm" wire:loading.attr="disabled" wire:target="saveWorkNotes">Save notes</button></div>
                    @endif
                </form>
            </div>
        </div>

        {{-- ===== side column ===== --}}
        <div class="space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Job Details</h3>
                    @if ($canPrice && ! $closed) <button class="btn-icon" wire:click="openDetails" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></button> @endif
                </div>
                <dl class="divide-y divide-ink-100 text-sm">
                    @foreach ([
                        'Mechanic' => $job->mechanic?->name, 'Received' => $job->received_at->format('d M Y, H:i'),
                        'Promised' => $job->promised_at?->format('d M Y, H:i'), 'Delivered' => $job->delivered_at?->format('d M Y, H:i'),
                        'Odometer in' => $job->odometer_in ? number_format($job->odometer_in).' km' : null, 'Fuel level' => $job->fuel_level,
                        'Opened by' => $job->creator?->name,
                    ] as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right font-medium text-ink-900">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                </dl>
                <div class="space-y-3 border-t border-ink-100 px-5 py-4 text-sm">
                    <div><p class="label">Complaint / work requested</p><p class="whitespace-pre-line text-ink-700">{{ $job->complaints ?: '—' }}</p></div>
                    @if ($job->inspection_notes) <div><p class="label">Inspection notes</p><p class="whitespace-pre-line text-ink-700">{{ $job->inspection_notes }}</p></div> @endif
                </div>
            </div>

            {{-- payments --}}
            @if ($canPrice || $job->payments->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Payments</h3></div>
                    <ul class="divide-y divide-ink-100 text-sm">
                        @forelse ($job->payments as $p)
                            <li class="flex items-center justify-between gap-3 px-5 py-3" wire:key="p{{ $p->id }}">
                                <div>
                                    <p class="font-semibold text-ink-900">{{ money($p->amount) }} <span class="font-normal text-ink-500">· {{ $p->mode }}</span></p>
                                    <p class="text-xs text-ink-400"><span class="font-mono">{{ $p->number }}</span> · {{ $p->date->format('d M Y') }} {{ $p->reference ? '· '.$p->reference : '' }} {{ $p->note === 'Advance' ? '· advance' : '' }}</p>
                                </div>
                                <a href="{{ route('print.receipt', $p) }}" target="_blank" class="btn-icon" title="Print receipt"><x-icon name="printer" class="h-4 w-4" /></a>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-ink-400">No payments yet.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

            {{-- history --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">History</h3></div>
                <ol class="space-y-4 px-5 py-4">
                    @foreach ($job->history as $h)
                        <li class="relative pl-5 text-sm" wire:key="h{{ $h->id }}">
                            <span class="absolute left-0 top-1.5 h-2 w-2 rounded-full {{ $loop->first ? 'bg-brand-500' : 'bg-ink-300' }}"></span>
                            <p class="font-semibold text-ink-900">
                                @if ($h->from_status && $h->from_status !== $h->to_status) {{ $h->from_status }} → {{ $h->to_status }} @elseif (! $h->from_status) {{ $h->to_status }} @endif
                                @if ($h->note) <span class="font-normal text-ink-600">{{ ($h->from_status && $h->from_status !== $h->to_status) || ! $h->from_status ? '— ' : '' }}{{ $h->note }}</span> @endif
                            </p>
                            <p class="text-xs text-ink-400">{{ $h->created_at->format('d M Y, H:i') }} · {{ $h->user?->name ?? 'System' }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>

    {{-- ===== add lines (several at once) ===== --}}
    <x-modal show="showItem" title="Add to job card" max-width="max-w-3xl">
        <div class="space-y-4">
            <div class="grid grid-cols-3 gap-1 rounded-lg bg-ink-100 p-1">
                @foreach (\App\Models\JobCardItem::TYPES as $key => $label)
                    @php $n = collect($pending)->where('type', $key)->count(); @endphp
                    <button type="button" wire:click="$set('itemType', @js($key))"
                            class="rounded-md py-1.5 text-sm font-semibold {{ $itemType === $key ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500' }}">
                        {{ $label }} @if ($n) <span class="ml-1 rounded-full bg-ink-900 px-1.5 text-[10px] text-white">{{ $n }}</span> @endif
                    </button>
                @endforeach
            </div>

            @if (in_array($itemType, ['service', 'part']))
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
                    <input wire:model.live.debounce.250ms="itemSearch" class="input pl-9" autocomplete="off" wire:key="search-{{ $itemType }}"
                           placeholder="{{ $itemType === 'part' ? 'Search parts by name, SKU, OEM no… (click to add, keep searching for more)' : 'Search services… (click to add, keep searching for more)' }}">
                    @if (trim($itemSearch) !== '')
                        <div class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-ink-100 bg-white shadow-lg">
                            @forelse ($results as $r)
                                <button type="button" wire:key="r{{ $itemType }}{{ $r->id }}" wire:click="{{ $itemType === 'part' ? 'pickPart' : 'pickService' }}({{ $r->id }})"
                                        class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-ink-50">
                                    <span><span class="font-medium text-ink-900">{{ $r->name }}</span>
                                        <span class="ml-1 font-mono text-xs text-ink-400">{{ $itemType === 'part' ? $r->sku : $r->code }}</span></span>
                                    @if ($itemType === 'part')
                                        <span class="shrink-0 text-xs {{ (float) $r->stock_qty <= 0 ? 'font-bold text-brand-600' : 'text-ink-500' }}">{{ rtrim(rtrim(number_format($r->stock_qty, 2), '0'), '.') }} {{ $r->unit }} · {{ amount($r->selling_price) }}</span>
                                    @else
                                        <span class="shrink-0 text-xs text-ink-500">{{ $r->priceLabel() }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="px-4 py-3 text-sm text-ink-500">Nothing found.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @else
                <div class="grid gap-3 rounded-lg border border-dashed border-ink-200 p-3 sm:grid-cols-12">
                    <div class="sm:col-span-5"><label class="label">Description *</label><input wire:model="other.description" wire:keydown.enter.prevent="addOther" class="input" placeholder="e.g. Towing, outside machining"></div>
                    <div class="sm:col-span-2"><label class="label">Qty *</label><input type="number" step="any" min="0" wire:model="other.qty" class="input"></div>
                    <div class="sm:col-span-2"><label class="label">Price *</label><input type="number" step="any" min="0" wire:model="other.rate" class="input"></div>
                    <div class="sm:col-span-2"><label class="label">Cost</label><input type="number" step="any" min="0" wire:model="other.cost" class="input" placeholder="0"></div>
                    <div class="flex items-end sm:col-span-1"><button type="button" class="btn-dark w-full px-0" wire:click="addOther" title="Add to list"><x-icon name="plus" class="h-4 w-4" /></button></div>
                    @foreach (['other.description', 'other.qty', 'other.rate', 'other.cost'] as $f) @error($f) <p class="error sm:col-span-12">{{ $message }}</p> @enderror @endforeach
                </div>
            @endif

            {{-- the list being built --}}
            <div class="overflow-hidden rounded-lg border border-ink-100">
                <table class="table">
                    <thead><tr><th>To add ({{ count($pending) }})</th><th class="w-24 text-right">Qty</th><th class="w-32 text-right">Price</th><th class="w-28 text-right">Cost{{ collect($pending)->contains('type', 'part') ? ' / unit' : '' }}</th><th class="w-28 text-right">Amount</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    @forelse ($pending as $i => $p)
                        <tr wire:key="pd{{ $i }}-{{ $p['type'] }}-{{ $p['part_id'] ?? $p['service_id'] ?? 'o' }}">
                            <td>
                                <span class="mr-1 text-[10px] font-bold uppercase tracking-wide text-ink-400">{{ \App\Models\JobCardItem::TYPES[$p['type']] }}</span>
                                <input wire:model="pending.{{ $i }}.description" class="w-full border-0 bg-transparent p-0 text-sm font-medium focus:ring-0">
                                @if ($p['type'] === 'part') <div class="text-[11px] {{ (float) $p['qty'] > (float) $p['stock'] ? 'font-bold text-brand-600' : 'text-ink-400' }}">{{ rtrim(rtrim(number_format($p['stock'], 2), '0'), '.') }} {{ $p['unit'] }} in stock
                                    @if (($p['min'] ?? 0) > 0) · <span class="{{ (float) $p['rate'] < $p['min'] ? 'font-bold text-brand-600' : '' }}">min {{ amount($p['min']) }}</span> @endif</div> @endif
                            </td>
                            <td><input type="number" step="any" min="0" wire:model.live.debounce.400ms="pending.{{ $i }}.qty" class="input px-2 py-1 text-right">
                                @if ($p['unit'] === 'hrs') <div class="text-right text-[10px] text-ink-400">hours</div> @endif</td>
                            <td><input type="number" step="any" min="0" wire:model.live.debounce.400ms="pending.{{ $i }}.rate" class="input px-2 py-1 text-right"></td>
                            <td><input type="number" step="any" min="0" wire:model="pending.{{ $i }}.cost" class="input px-2 py-1 text-right"></td>
                            <td class="text-right font-semibold">{{ amount((float) $p['qty'] * (float) $p['rate']) }}</td>
                            <td><button type="button" class="text-ink-300 hover:text-brand-600" wire:click="removePending({{ $i }})"><x-icon name="x" class="h-4 w-4" /></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-400">Pick labour, parts and other items — they collect here. Switch tabs freely; nothing is lost until you save.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($pending)
                        <tfoot><tr><td colspan="4" class="text-right font-semibold">Total to add</td><td class="text-right font-extrabold">{{ amount($pendingTotal) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            </div>
            @error('pending') <p class="error">{{ $message }}</p> @enderror
            @foreach ($errors->getMessages() as $key => $msgs) @if (str_starts_with($key, 'pending.')) <p class="error">Line {{ (int) explode('.', $key)[1] + 1 }}: {{ $msgs[0] }}</p> @endif @endforeach
            <p class="hint">Parts come out of stock when you save. Part cost defaults to the spare-parts selling price (what the garage pays the shop); use cost on labour/other for outsourced work so profit stays correct.</p>
        </div>
        <x-slot:footer>
            @if ($pending) <button type="button" class="mr-auto text-xs font-semibold text-ink-400 hover:text-brand-600" wire:click="clearPending" wire:confirm="Remove all lines from the list?">Clear list</button> @endif
            <button type="button" class="btn-light" @click="open = false">Close</button>
            <button type="button" class="btn-primary" wire:click="savePending" wire:loading.attr="disabled" wire:target="savePending" @disabled(! $pending)>
                <span wire:loading.remove wire:target="savePending">Save {{ count($pending) ?: '' }} {{ count($pending) === 1 ? 'line' : 'lines' }} to job card</span>
                <span wire:loading wire:target="savePending">Saving…</span>
            </button>
        </x-slot:footer>
    </x-modal>

    {{-- ===== edit one line ===== --}}
    <x-modal show="showEdit" title="Edit line">
        <form wire:submit="saveItem" id="itemForm" class="space-y-4">
            <div>
                <label class="label">Description *</label>
                <input wire:model="line.description" class="input">
                @error('line.description') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="label">{{ ($line['type'] ?? '') === 'service' ? 'Qty / hours' : 'Quantity' }} *</label><input type="number" step="any" min="0" wire:model="line.qty" class="input"></div>
                <div><label class="label">Price *</label><input type="number" step="any" min="0" wire:model="line.rate" class="input"></div>
                <div><label class="label">{{ ($line['type'] ?? '') === 'part' ? 'Cost / unit' : 'Cost' }}</label><input type="number" step="any" min="0" wire:model="line.cost" class="input"></div>
            </div>
            @error('line.qty') <p class="error">{{ $message }}</p> @enderror
            @error('line.rate') <p class="error">{{ $message }}</p> @enderror
            @if (($line['type'] ?? '') === 'part') <p class="hint">Changing the quantity takes more parts from stock or returns the difference.</p> @endif
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="itemForm" class="btn-primary" wire:loading.attr="disabled" wire:target="saveItem">Save line</button>
        </x-slot:footer>
    </x-modal>

    {{-- ===== cancel ===== --}}
    <x-modal show="showCancel" title="Cancel job card">
        <form wire:submit="confirmCancel" id="cancelForm" class="space-y-3">
            <p class="text-sm text-ink-600">Parts on this job go back to stock. A job that is invoiced or has an advance payment cannot be cancelled.</p>
            <div><label class="label">Reason *</label><input wire:model="cancelReason" class="input" placeholder="e.g. Customer took the vehicle elsewhere"></div>
            @error('cancelReason') <p class="error">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Keep job</button>
            <button type="submit" form="cancelForm" class="btn-danger" wire:loading.attr="disabled" wire:target="confirmCancel">Cancel job card</button>
        </x-slot:footer>
    </x-modal>

    {{-- ===== details ===== --}}
    <x-modal show="showDetails" title="Edit job details" max-width="max-w-2xl">
        <form wire:submit="saveDetails" id="detailsForm" class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Mechanic</label>
                <select wire:model="details.mechanic_id" class="input"><option value="">— Not assigned —</option>@foreach ($mechanics as $m) <option value="{{ $m->id }}">{{ $m->name }}</option> @endforeach</select></div>
            <div><label class="label">Priority</label>
                <select wire:model="details.priority" class="input">@foreach ($priorities as $p) <option>{{ $p }}</option> @endforeach</select></div>
            <div><label class="label">Promised date</label><input type="datetime-local" wire:model="details.promised_at" class="input"></div>
            <div><label class="label">Odometer in (km)</label><input type="number" min="0" wire:model="details.odometer_in" class="input">
                @error('details.odometer_in') <p class="error">{{ $message }}</p> @enderror</div>
            <div class="sm:col-span-2"><label class="label">Fuel level</label>
                <select wire:model="details.fuel_level" class="input"><option value="">—</option>@foreach ($fuelLevels as $f) <option>{{ $f }}</option> @endforeach</select></div>
            <div class="sm:col-span-2"><label class="label">Complaint / work requested *</label><textarea wire:model="details.complaints" rows="3" class="input"></textarea>
                @error('details.complaints') <p class="error">{{ $message }}</p> @enderror</div>
            <div class="sm:col-span-2"><label class="label">Inspection notes</label><textarea wire:model="details.inspection_notes" rows="2" class="input"></textarea></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="detailsForm" class="btn-primary" wire:loading.attr="disabled" wire:target="saveDetails">Save</button>
        </x-slot:footer>
    </x-modal>

    <livewire:payments.record-form />
</div>
