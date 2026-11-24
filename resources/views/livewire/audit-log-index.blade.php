<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="relative w-full sm:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Search record, action, details…">
        </div>
        <select wire:model.live="module" class="input sm:max-w-[200px]">
            <option value="">All modules</option>
            @foreach ($modules as $m) <option>{{ $m }}</option> @endforeach
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($logs as $l)
                    <tr wire:key="l{{ $l->id }}">
                        <td class="whitespace-nowrap text-xs text-ink-500">{{ $l->created_at->format('d M Y H:i') }}</td>
                        <td class="whitespace-nowrap">{{ $l->user?->name ?? 'System' }}</td>
                        <td><span class="badge-gray">{{ $l->module }}</span></td>
                        <td class="text-xs font-bold">{{ $l->action }}</td>
                        <td class="font-mono text-xs">{{ $l->record }}</td>
                        <td class="max-w-xs truncate text-xs text-ink-500" title="{{ $l->details }}">{{ $l->details }}</td>
                        <td class="text-xs text-ink-400">{{ $l->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-ink-400">No activity recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $logs->links() }}</div> @endif
    </div>
</div>
