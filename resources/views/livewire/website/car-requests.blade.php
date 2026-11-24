<div class="space-y-5">
    <p class="text-sm text-ink-500">Sent from <b>Can't find your car?</b> on the website. Call the customer back, then add the car to the website's car list so it shows in “Find parts for your car”.</p>

    <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach (array_merge(\App\Models\CarRequest::STATUSES, ['all' => 'All']) as $k => $label)
            @php $n = $k === 'all' ? $counts->sum() : ($counts[$k] ?? 0); @endphp
            <button wire:click="$set('tab', @js($k))" class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold {{ $tab === $k ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-100 hover:bg-ink-50' }}">
                {{ $label }} <span class="rounded-full px-1.5 text-xs {{ $tab === $k ? 'bg-white/15' : ($k === 'new' && $n ? 'bg-brand-500 text-white' : 'bg-ink-100') }}">{{ $n }}</span>
            </button>
        @endforeach
    </div>

    <div class="relative w-full sm:max-w-sm">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
        <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Car, VIN, phone, part…">
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Received</th><th>Car</th><th class="w-full">Parts wanted</th><th>Contact</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($requests as $r)
                    <tr wire:key="cr{{ $r->id }}" class="cursor-pointer" wire:click="open({{ $r->id }})">
                        <td class="whitespace-nowrap text-xs text-ink-500">{{ $r->created_at->format('d M Y H:i') }}</td>
                        <td class="whitespace-nowrap"><div class="font-semibold text-ink-900">{{ $r->car() }}</div>@if ($r->vin)<div class="font-mono text-xs text-ink-400">{{ $r->vin }}</div>@endif</td>
                        <td class="max-w-md truncate text-sm">{{ $r->parts ?: '—' }}</td>
                        <td class="whitespace-nowrap"><div>{{ $r->name ?: '—' }}</div><div class="text-xs text-ink-400">{{ $r->phone ?: $r->email }}</div></td>
                        <td class="whitespace-nowrap"><span class="{{ ['new' => 'badge-red', 'contacted' => 'badge-amber', 'added' => 'badge-green', 'closed' => 'badge-gray'][$r->status] ?? 'badge-gray' }}">{{ \App\Models\CarRequest::STATUSES[$r->status] ?? $r->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-12 text-center text-ink-400">No requests here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $requests->links() }}</div> @endif
    </div>

    <x-modal show="showView" :title="$view ? $view->car() : 'Car request'" max-width="max-w-2xl">
        @if ($view)
            <div class="space-y-4 text-sm">
                <dl class="grid gap-x-6 gap-y-3 rounded-lg bg-ink-50 p-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold text-ink-400">Car</dt><dd class="font-semibold">{{ $view->make }} {{ $view->model }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Year</dt><dd>{{ $view->year ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">VIN / chassis</dt><dd class="font-mono">{{ $view->vin ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Received</dt><dd>{{ $view->created_at->format('d M Y H:i') }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Name</dt><dd>{{ $view->name ?: '—' }} @if ($view->customer) <span class="badge-gray">Website account</span> @endif</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Phone / email</dt><dd>
                        @if ($view->phone) <a href="tel:{{ $view->phone }}" class="font-semibold text-brand-600">{{ $view->phone }}</a> @endif
                        @if ($view->email) <div><a href="mailto:{{ $view->email }}" class="text-brand-600">{{ $view->email }}</a></div> @endif
                    </dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-400">Parts wanted</dt><dd class="whitespace-pre-line">{{ $view->parts ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Offers by SMS / WhatsApp</dt><dd>{{ $view->wants_offers ? 'Yes' : 'No' }}</dd></div>
                    @if ($view->handler)<div><dt class="text-xs font-semibold text-ink-400">Last handled</dt><dd>{{ $view->handler->name }}, {{ $view->handled_at?->format('d M H:i') }}</dd></div>@endif
                </dl>
                @if ($this->canManage())
                    <div>
                        <label class="label">Note (what you told the customer, price quoted…)</label>
                        <textarea wire:model="note" rows="2" maxlength="500" class="input"></textarea>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            @if ($view && $this->canManage())
                @if (auth()->user()->hasRole('admin', 'manager'))
                    <button type="button" class="btn-light mr-auto !text-brand-600" wire:click="delete({{ $view->id }})" wire:confirm="Delete this request?">Delete</button>
                @endif
                <button type="button" class="btn-light" wire:click="setStatus('closed')">Close</button>
                <button type="button" class="btn-light" wire:click="setStatus('contacted')">Mark contacted</button>
                <button type="button" class="btn-primary" wire:click="addToList">Add car to website list</button>
            @endif
        </x-slot:footer>
    </x-modal>
</div>
