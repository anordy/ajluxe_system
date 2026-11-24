<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-ink-500">Mechanics are assigned to job cards. Link a mechanic to a <b>Mechanic</b> login so they can update their own jobs.</p>
        @if ($this->canManage())
            <button class="btn-primary shrink-0" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New Mechanic</button>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($mechanics as $m)
            <div wire:key="m{{ $m->id }}" class="card flex flex-col p-5 {{ $m->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-ink-900 text-sm font-bold text-white">
                        {{ collect(explode(' ', $m->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-ink-900">{{ $m->name }}</p>
                        <p class="text-xs text-ink-500">{{ $m->specialization ?: 'Mechanic' }}</p>
                    </div>
                    @if ($m->is_active) <span class="badge-green">Active</span> @else <span class="badge-red">Inactive</span> @endif
                </div>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-400">Phone</dt><dd>{{ $m->phone ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-400">Login</dt><dd class="font-mono text-xs">{{ $m->user?->username ?? 'Not linked' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-400">Open jobs</dt><dd class="font-bold">{{ $m->openJobsCount() }}</dd></div>
                </dl>
                @if ($this->canManage())
                    <div class="mt-4 flex justify-end gap-1 border-t border-ink-100 pt-3">
                        <button class="btn-icon" title="Edit" wire:click="edit({{ $m->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                        <button class="btn-icon" title="{{ $m->is_active ? 'Deactivate' : 'Activate' }}" wire:click="toggleActive({{ $m->id }})"><x-icon name="{{ $m->is_active ? 'x' : 'check' }}" class="h-4 w-4" /></button>
                        <button class="btn-icon hover:!text-brand-600" title="Remove" wire:click="delete({{ $m->id }})" wire:confirm="Remove {{ $m->name }}?"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                @endif
            </div>
        @empty
            <div class="card col-span-full py-12 text-center text-sm text-ink-400">No mechanics yet.</div>
        @endforelse
    </div>

    <x-modal show="showForm" :title="$editingId ? 'Edit Mechanic' : 'New Mechanic'">
        <form wire:submit="save" id="mechanicForm" class="grid gap-4">
            <div>
                <label class="label">Linked login (optional)</label>
                <select wire:model.live="form.user_id" class="input">
                    <option value="">— No login —</option>
                    @foreach ($logins as $u) <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username }})</option> @endforeach
                </select>
                <p class="hint">Create the login first in Users with the role <b>Mechanic</b>.</p>
                @error('form.user_id') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Full name *</label>
                <input wire:model="form.name" class="input">
                @error('form.name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">Phone</label><input wire:model="form.phone" class="input"></div>
                <div>
                    <label class="label">Specialization</label>
                    <select wire:model="form.specialization" class="input">
                        <option value="">—</option>
                        @foreach ($specs as $s) <option>{{ $s }}</option> @endforeach
                    </select>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" wire:model="form.is_active" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Active
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="mechanicForm" class="btn-primary" wire:loading.attr="disabled">Save Mechanic</button>
        </x-slot:footer>
    </x-modal>
</div>
