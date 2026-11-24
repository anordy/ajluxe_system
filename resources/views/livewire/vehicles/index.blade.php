<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative w-full lg:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-300" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-9" placeholder="Search reg no, make, model, owner, phone…">
        </div>
        <select wire:model.live="make" class="input lg:max-w-[180px]">
            <option value="">All makes</option>
            @foreach ($makes as $m) <option>{{ $m }}</option> @endforeach
        </select>
        @if ($this->canManage())
            <button class="btn-primary lg:ml-auto" wire:click="$dispatch('open-vehicle-form')"><x-icon name="plus" class="h-4 w-4" /> New Vehicle</button>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Reg No</th><th>Make / Model</th><th>Year</th><th>Owner</th><th>Odometer</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                @forelse ($vehicles as $v)
                    <tr wire:key="v{{ $v->id }}">
                        <td><span class="rounded-md border-2 border-ink-900 bg-amber-300 px-2 py-0.5 font-mono text-xs font-bold tracking-wider text-ink-900">{{ $v->reg_no }}</span></td>
                        <td>
                            <div class="font-semibold text-ink-900">{{ trim($v->make.' '.$v->model) ?: '—' }}</div>
                            <div class="text-xs text-ink-400">{{ collect([$v->fuel_type, $v->color])->filter()->implode(' · ') }}</div>
                        </td>
                        <td>{{ $v->year ?: '—' }}</td>
                        <td>
                            @if ($v->customer)
                                <a href="{{ route('customers.show', $v->customer_id) }}" wire:navigate class="font-medium hover:text-brand-600">{{ $v->customer->name }}</a>
                                <div class="text-xs text-ink-400">{{ $v->customer->phone }}</div>
                            @endif
                        </td>
                        <td>{{ $v->odometer ? number_format($v->odometer).' km' : '—' }}</td>
                        <td class="whitespace-nowrap text-right">
                            @if ($this->canManage())
                                <button class="btn-icon" title="Edit" wire:click="$dispatch('open-vehicle-form', { id: {{ $v->id }} })"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button class="btn-icon hover:!text-brand-600" title="Remove" wire:click="delete({{ $v->id }})" wire:confirm="Remove vehicle {{ $v->reg_no }}?"><x-icon name="trash" class="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-12 text-center text-ink-400">No vehicles found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($vehicles->hasPages()) <div class="border-t border-ink-100 px-4 py-3">{{ $vehicles->links() }}</div> @endif
    </div>

    <livewire:vehicles.form />
</div>
