@php $roles = \App\Support\Permissions::EDITABLE; @endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <p class="max-w-2xl text-sm text-ink-500">Tick what each role may open and do. The <b>Administrator</b> always has everything, and alone manages Users, Roles, Settings, the Audit log and Import data. Changes apply as soon as you save; people already signed in see them on their next click.</p>
        <div class="flex gap-2">
            <button type="button" class="btn-light" wire:click="defaults">Fill in defaults</button>
            <button type="button" class="btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">Save permissions</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-1/2">Permission</th>
                        <th class="text-center">Administrator<div class="text-[11px] font-normal normal-case text-ink-400">{{ $counts['admin'] ?? 0 }} active</div></th>
                        @foreach ($roles as $r)
                            <th class="text-center">{{ \App\Models\User::ROLES[$r] }}<div class="text-[11px] font-normal normal-case text-ink-400">{{ $counts[$r] ?? 0 }} active</div></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach ($groups as $group => $perms)
                    <tr class="bg-ink-50/70"><td colspan="{{ count($roles) + 2 }}" class="py-2 text-xs font-bold uppercase tracking-wide text-ink-500">{{ $group }}</td></tr>
                    @foreach ($perms as $p)
                        <tr wire:key="p-{{ $p['key'] }}">
                            <td class="text-ink-800">{{ $p['label'] }}</td>
                            <td class="text-center"><input type="checkbox" checked disabled class="rounded border-ink-200 text-ink-300" aria-label="Administrator: {{ $p['label'] }}"></td>
                            @foreach ($roles as $r)
                                <td class="text-center">
                                    <input type="checkbox" wire:model="ticks.{{ $r }}.{{ \App\Livewire\Users\Roles::slot($p['key']) }}" class="h-4 w-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400" aria-label="{{ \App\Models\User::ROLES[$r] }}: {{ $p['label'] }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
