<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Search name, username, phone…">
        </div>
        <div class="flex gap-2">
            <a href="{{ route('roles.index') }}" wire:navigate class="btn-light"><x-icon name="shield" class="h-4 w-4" /> Roles &amp; permissions</a>
            <button class="btn-primary" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New User</button>
        </div>
    </div>

    @error('role') <div class="rounded-xl border border-brand-200 bg-brand-50 p-3 text-sm font-medium text-brand-700">{{ $message }}</div> @enderror

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Phone</th><th>Last sign-in</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                @forelse ($users as $u)
                    <tr wire:key="u{{ $u->id }}" class="{{ $u->is_active ? '' : 'opacity-60' }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-ink-900 text-[11px] font-bold text-white">{{ $u->initials() }}</span>
                                <div><div class="font-semibold text-ink-900">{{ $u->name }}</div><div class="text-xs text-ink-400">{{ $u->email }}</div></div>
                            </div>
                        </td>
                        <td class="font-mono text-xs">{{ $u->username }}</td>
                        <td><span class="{{ $u->role === 'admin' ? 'badge-dark' : 'badge-gray' }}">{{ $u->roleLabel() }}</span></td>
                        <td>{{ $u->phone ?: '—' }}</td>
                        <td class="text-xs text-ink-500">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td>
                            @if ($u->is_active) <span class="badge-green">Active</span> @else <span class="badge-red">Inactive</span> @endif
                            @if ($u->must_change_password) <span class="badge-amber ml-1" title="Must change password at next sign-in">Temp password</span> @endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <button class="btn-icon" title="Edit" wire:click="edit({{ $u->id }})"><x-icon name="pencil" class="h-4 w-4" /></button>
                            <button class="btn-icon" title="Reset password" wire:click="resetPassword({{ $u->id }})"
                                    wire:confirm="Reset the password for {{ $u->name }}? A new temporary password will be shown."><x-icon name="key" class="h-4 w-4" /></button>
                            @if ($u->id !== auth()->id())
                                <button class="btn-sm {{ $u->is_active ? 'btn-light' : 'btn-dark' }}" wire:click="toggleActive({{ $u->id }})"
                                        wire:confirm="{{ $u->is_active ? 'Deactivate' : 'Activate' }} {{ $u->name }}?">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-ink-400">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $users->links() }}</div> @endif
    </div>

    {{-- create / edit --}}
    <x-modal show="showForm" :title="$editingId ? 'Edit User' : 'New User'">
        <form wire:submit="save" id="userForm" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label">Full name *</label>
                <input wire:model="name" class="input">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Username *</label>
                <input wire:model="username" class="input" autocomplete="off">
                @error('username') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Role *</label>
                <select wire:model="role" class="input">
                    @foreach ($roles as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                </select>
                @error('role') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Phone</label>
                <input wire:model="phone" class="input">
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" wire:model="email" class="input">
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            @unless ($editingId)
                <div class="sm:col-span-2">
                    <label class="label">Temporary password *</label>
                    <input wire:model="password" class="input font-mono">
                    <p class="hint">The user must change it at first sign-in.</p>
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endunless
            <label class="flex items-center gap-2 text-sm text-ink-700 sm:col-span-2">
                <input type="checkbox" wire:model="is_active" class="rounded border-ink-300 text-brand-500 focus:ring-brand-400"> Active (can sign in)
            </label>
            <div class="rounded-lg bg-ink-50 p-3 text-xs leading-relaxed text-ink-500 sm:col-span-2">
                <b class="text-ink-700">Administrator</b>: everything. <b class="text-ink-700">Manager</b>: all operations, no users/settings.
                <b class="text-ink-700">Mechanic</b>: garage job cards. <b class="text-ink-700">Sales Staff</b>: spare parts sales / POS and customers.
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-light" @click="open = false">Cancel</button>
            <button type="submit" form="userForm" class="btn-primary" wire:loading.attr="disabled">Save User</button>
        </x-slot:footer>
    </x-modal>

    {{-- temporary password shown once --}}
    @if ($issued)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/50 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="key" /></span>
                <h3 class="mt-3 text-lg font-bold text-ink-900">Sign-in details for {{ $issued['name'] }}</h3>
                <p class="mt-1 text-sm text-ink-500">Give these to the user. The password is shown only once.</p>
                <div class="mt-4 space-y-2 rounded-xl bg-ink-50 p-4 text-left text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Username</span><b class="font-mono">{{ $issued['username'] }}</b></div>
                    <div class="flex justify-between"><span class="text-ink-500">Password</span><b class="font-mono">{{ $issued['password'] }}</b></div>
                </div>
                <button class="btn-dark mt-5 w-full" wire:click="$set('issued', null)">Done</button>
            </div>
        </div>
        @endteleport
    @endif
</div>
