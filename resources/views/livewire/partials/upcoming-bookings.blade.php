@if ($upcoming->isNotEmpty())
    <div class="mt-6 first:mt-0">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">Next garage bookings</p>
            <a href="{{ route('bookings.index') }}" wire:navigate class="text-xs font-semibold text-ink-500 hover:text-ink-900">All bookings</a>
        </div>
        <ul class="divide-y divide-ink-100 text-sm">
            @foreach ($upcoming as $b)
                <li class="flex items-center gap-3 py-2">
                    <span class="w-16 shrink-0 text-xs font-semibold {{ $b->preferred_date?->isToday() ? 'text-brand-600' : 'text-ink-500' }}">{{ $b->preferred_date ? ($b->preferred_date->isToday() ? 'Today' : ($b->preferred_date->isTomorrow() ? 'Tomorrow' : $b->preferred_date->format('d M'))) : 'Any day' }}</span>
                    <span class="min-w-0 flex-1 truncate"><b class="font-semibold text-ink-900">{{ $b->car }}</b>@if ($b->plate) · {{ $b->plate }}@endif <span class="text-ink-500">· {{ $b->name }}</span></span>
                    <span class="{{ \App\Models\GarageBooking::BADGES[$b->status] }}">{{ \App\Models\GarageBooking::STATUSES[$b->status] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
