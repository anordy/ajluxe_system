<div class="grid gap-6 lg:grid-cols-[260px_1fr]">
    {{-- list picker --}}
    <div class="card h-fit overflow-hidden">
        <div class="card-header"><h2 class="card-title">Dropdown Lists</h2></div>
        <nav class="p-2">
            @foreach ($lists as $key => $label)
                <button wire:click="select('{{ $key }}')"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm {{ $list === $key ? 'bg-ink-900 font-semibold text-white' : 'text-ink-700 hover:bg-ink-50' }}">
                    {{ $label }}
                    <span class="text-xs {{ $list === $key ? 'text-ink-300' : 'text-ink-400' }}">{{ $counts[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </nav>
    </div>

    {{-- values --}}
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">{{ $lists[$list] }}</h2>
            <span class="text-xs text-ink-400">{{ $items->count() }} values</span>
        </div>
        <div class="card-body space-y-4">
            <form wire:submit="add" class="flex gap-2">
                <input wire:model="newValue" class="input" placeholder="Add a new value…">
                <button class="btn-dark shrink-0"><x-icon name="plus" class="h-4 w-4" /> Add</button>
            </form>
            @error('newValue') <p class="error">{{ $message }}</p> @enderror

            <ul class="divide-y divide-ink-100 rounded-xl border border-ink-100">
                @forelse ($items as $o)
                    <li wire:key="o{{ $o->id }}" class="flex items-center gap-2 px-4 py-2.5">
                        <span class="flex-1 text-sm font-medium text-ink-800">{{ $o->value }}</span>
                        <button class="btn-icon" title="Move up" wire:click="move({{ $o->id }}, -1)" @disabled($loop->first)><x-icon name="up" class="h-4 w-4" /></button>
                        <button class="btn-icon" title="Move down" wire:click="move({{ $o->id }}, 1)" @disabled($loop->last)><x-icon name="down" class="h-4 w-4" /></button>
                        <button class="btn-icon hover:!text-brand-600" title="Remove" wire:click="remove({{ $o->id }})"
                                wire:confirm="Remove &quot;{{ $o->value }}&quot; from the list? Records that already use it are not changed."><x-icon name="trash" class="h-4 w-4" /></button>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-ink-400">No values yet — add the first one above.</li>
                @endforelse
            </ul>
            <p class="hint">Removing a value only hides it from dropdowns. Records that already use it keep it.</p>
        </div>
    </div>
</div>
