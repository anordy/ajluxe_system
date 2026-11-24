<div class="space-y-5">
    {{-- status tabs --}}
    <div class="flex gap-2 overflow-x-auto pb-1">
        @php
            $tabs = ['active' => ['Active', $activeCount]];
            foreach (\App\Models\JobCard::STATUSES as $s) { if ($s !== \App\Models\JobCard::DELIVERED) $tabs[$s] = [$s, $counts[$s] ?? 0]; }
            $tabs['closed'] = ['Delivered / Cancelled', ($counts['Delivered'] ?? 0) + ($counts['Cancelled'] ?? 0)];
            $tabs['all'] = ['All', $counts->sum()];
        @endphp
        @foreach ($tabs as $key => [$label, $n])
            @php
                $J = \App\Models\JobCard::class;
                $solid = $J::SOLID[$key] ?? ($key === 'closed' ? $J::SOLID[$J::DELIVERED] : 'bg-ink-900 text-white');
                $dot = $J::DOTS[$key] ?? ($key === 'closed' ? $J::DOTS[$J::DELIVERED] : null);
            @endphp
            <button wire:click="$set('tab', @js($key))" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}"
                    class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition {{ $tab === $key ? $solid : 'bg-white text-ink-600 ring-1 ring-ink-100 hover:bg-ink-50' }}">
                @if ($dot && $tab !== $key) <span class="h-2 w-2 rounded-full {{ $dot }}"></span> @endif
                {{ $label }} <span class="rounded-full px-1.5 text-xs {{ $tab === $key ? 'bg-white/20' : 'bg-ink-100' }}">{{ $n }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Job no, reg no, customer, phone…">
        </div>
        @if ($customerName)
            <span class="inline-flex shrink-0 items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-800 ring-1 ring-inset ring-blue-200">
                Customer: {{ $customerName }}
                <button type="button" wire:click="$set('customer', '')" class="text-blue-500 hover:text-blue-900" aria-label="Show all customers">✕</button>
            </span>
        @endif
        <select wire:model.live="mechanic" class="input lg:max-w-[200px]">
            <option value="">All mechanics</option>
            @foreach ($mechanics as $m) <option value="{{ $m->id }}">{{ $m->name }}</option> @endforeach
        </select>
        @if ($canCreate)
            <a href="{{ route('job-cards.create') }}" wire:navigate class="btn-primary lg:ml-auto"><x-icon name="plus" class="h-4 w-4" /> New Job Card</a>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Job card</th><th>Vehicle</th><th>Customer</th><th>Mechanic</th><th>Status</th><th class="text-right">Total</th><th>Invoice</th></tr></thead>
                <tbody>
                @forelse ($jobs as $j)
                    <tr wire:key="j{{ $j->id }}" class="cursor-pointer" onclick="Livewire.navigate('{{ route('job-cards.show', $j) }}')">
                        <td>
                            <div class="font-mono text-xs font-bold text-ink-900">{{ $j->number }}</div>
                            <div class="text-xs text-ink-400">{{ $j->received_at->format('d M Y') }}
                                @if (in_array($j->priority, ['High', 'Urgent'])) <span class="ml-1 font-bold text-brand-600">· {{ $j->priority }}</span> @endif
                            </div>
                        </td>
                        <td>
                            <span class="rounded-md border-2 border-ink-900 bg-amber-300 px-2 py-0.5 font-mono text-xs font-bold tracking-wider text-ink-900">{{ $j->vehicle?->reg_no }}</span>
                            <div class="mt-1 text-xs text-ink-500">{{ trim($j->vehicle?->make.' '.$j->vehicle?->model) }}</div>
                        </td>
                        <td><div class="font-medium">{{ $j->customer?->name }}</div><div class="text-xs text-ink-400">{{ $j->customer?->phone }}</div></td>
                        <td>{{ $j->mechanic?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $j->badge() }}">{{ $j->status }}</span>
                            @if ($j->isOverdue()) <div class="mt-1 text-[11px] font-bold text-brand-600">Overdue</div> @endif
                        </td>
                        <td class="text-right font-semibold">{{ money($j->grand_total) }}</td>
                        <td>
                            @if ($j->invoice)
                                <span class="font-mono text-xs">{{ $j->invoice->number }}</span>
                                <span class="{{ \App\Models\Invoice::STATUS_BADGES[$j->invoice->status] }} ml-1">{{ \App\Models\Invoice::STATUS_LABELS[$j->invoice->status] }}</span>
                            @else <span class="text-xs text-ink-300">—</span> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-ink-400">
                        No job cards here. @if ($canCreate) <a href="{{ route('job-cards.create') }}" wire:navigate class="font-semibold text-brand-600 hover:underline">Open a new job card</a> @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($jobs->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $jobs->links() }}</div> @endif
    </div>
</div>
