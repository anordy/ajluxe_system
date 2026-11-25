@php $wa = fn ($phone) => 'https://wa.me/'.(str_starts_with($d = preg_replace('/\D+/', '', $phone), '0') ? '255'.substr($d, 1) : $d); @endphp
<div class="space-y-5">
    <p class="text-sm text-ink-500">Sent from <b>Book your car in</b> on the website. Call the customer, confirm the day, and open a job card when the car arrives.</p>

    <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach ($tabs as $k => [$label, $n])
            <button wire:click="$set('tab', @js($k))" class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold {{ $tab === $k ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-100 hover:bg-ink-50' }}">
                {{ $label }} <span class="rounded-full px-1.5 text-xs {{ $tab === $k ? 'bg-white/15' : ($k === 'new' && $n ? 'bg-brand-500 text-white' : 'bg-ink-100') }}">{{ $n }}</span>
            </button>
        @endforeach
    </div>

    <div class="relative w-full sm:max-w-sm">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
        <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Name, phone, car, plate…">
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Day wanted</th><th>Car</th><th class="w-full">Work needed</th><th>Customer</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    <tr wire:key="bk{{ $b->id }}" class="cursor-pointer" wire:click="open({{ $b->id }})">
                        <td class="whitespace-nowrap">
                            <div class="font-semibold {{ $b->preferred_date?->isToday() ? 'text-brand-600' : 'text-ink-900' }}">{{ $b->preferred_date ? ($b->preferred_date->isToday() ? 'Today' : ($b->preferred_date->isTomorrow() ? 'Tomorrow' : $b->preferred_date->format('D d M'))) : 'Any day' }}</div>
                            <div class="text-xs text-ink-400">sent {{ $b->created_at->format('d M, H:i') }}</div>
                        </td>
                        <td class="whitespace-nowrap"><div class="font-semibold text-ink-900">{{ $b->car }}</div>@if ($b->plate)<div class="font-mono text-xs text-ink-400">{{ $b->plate }}</div>@endif</td>
                        <td class="max-w-md truncate text-sm">{{ $b->work }}</td>
                        <td class="whitespace-nowrap"><div>{{ $b->name }}</div><div class="text-xs text-ink-400">{{ $b->phone }}@if ($b->customer) · <span class="text-emerald-700">existing customer</span>@endif</div></td>
                        <td class="whitespace-nowrap">
                            <span class="{{ \App\Models\GarageBooking::BADGES[$b->status] ?? 'badge-gray' }}">{{ \App\Models\GarageBooking::STATUSES[$b->status] ?? $b->status }}</span>
                            @if ($b->jobCard) <div class="mt-1 font-mono text-xs text-ink-400">{{ $b->jobCard->number }}</div> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-12 text-center text-ink-400">No bookings here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $bookings->links() }}</div> @endif
    </div>

    <x-modal show="showView" :title="$view ? $view->car.($view->plate ? ' · '.$view->plate : '') : 'Booking'" max-width="max-w-2xl">
        @if ($view)
            <div class="space-y-4 text-sm">
                <dl class="grid gap-x-6 gap-y-3 rounded-lg bg-ink-50 p-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold text-ink-400">Customer</dt><dd class="font-semibold">{{ $view->name }}
                        @if ($view->customer) <a href="{{ route('customers.show', $view->customer) }}" wire:navigate class="badge-green ml-1">existing: {{ $view->customer->name }}</a> @endif</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Phone</dt><dd>
                        <a href="tel:{{ $view->phone }}" class="font-semibold text-brand-600">{{ $view->phone }}</a>
                        · <a href="{{ $wa($view->phone) }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700">WhatsApp</a>
                    </dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Car</dt><dd class="font-semibold">{{ $view->car }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Plate number</dt><dd class="font-mono">{{ $view->plate ?: '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-400">Work needed</dt><dd class="whitespace-pre-line">{{ $view->work }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Sent</dt><dd>{{ $view->created_at->format('d M Y H:i') }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-400">Status</dt><dd><span class="{{ \App\Models\GarageBooking::BADGES[$view->status] }}">{{ \App\Models\GarageBooking::STATUSES[$view->status] }}</span>
                        @if ($view->jobCard) <a href="{{ route('job-cards.show', $view->jobCard) }}" wire:navigate class="ml-1 font-mono font-semibold text-brand-600">{{ $view->jobCard->number }}</a> @endif</dd></div>
                    @if ($view->handler)<div><dt class="text-xs font-semibold text-ink-400">Last handled</dt><dd>{{ $view->handler->name }}, {{ $view->handled_at?->format('d M H:i') }}</dd></div>@endif
                </dl>
                @if ($this->canManage() && $view->status !== 'booked_in')
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label">Day agreed</label>
                            <input type="date" wire:model="day" class="input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Note (what you agreed with the customer)</label>
                            <input type="text" wire:model="note" maxlength="500" class="input">
                        </div>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            @if ($view && $this->canManage() && $view->status !== 'booked_in')
                @if ($view->status !== 'cancelled')
                    <button type="button" class="btn-light mr-auto !text-brand-600" wire:click="setStatus('cancelled')" wire:confirm="Cancel this booking?">Cancel booking</button>
                @else
                    <button type="button" class="btn-light mr-auto" wire:click="setStatus('new')">Re-open</button>
                @endif
                @if ($view->status !== 'cancelled')
                    <button type="button" class="btn-light" wire:click="setStatus('confirmed')">{{ $view->status === 'confirmed' ? 'Save' : 'Confirm day' }}</button>
                    @if ($canJob)
                        <a href="{{ route('job-cards.create', ['booking' => $view->id]) }}" wire:navigate class="btn-primary">Car arrived · open job card</a>
                    @endif
                @endif
            @endif
        </x-slot:footer>
    </x-modal>
</div>
