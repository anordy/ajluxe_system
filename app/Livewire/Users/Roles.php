<?php

namespace App\Livewire\Users;

use App\Models\User;
use App\Support\Audit;
use App\Support\Permissions;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Administrator ticks what Manager, Staff and Mechanic may open and do. */
#[Title('Roles & Permissions')]
class Roles extends Component
{
    /** role => [permission => bool]; dots in keys become "__" (a dot would read as a nested path in wire:model) */
    public array $ticks = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole(User::ADMIN), 403);
        $this->load(Permissions::map());
    }

    private function load(array $map): void
    {
        $this->ticks = [];
        foreach (Permissions::EDITABLE as $role) {
            foreach (array_keys(Permissions::ALL) as $perm) {
                $this->ticks[$role][self::slot($perm)] = in_array($perm, $map[$role] ?? [], true);
            }
        }
    }

    public function defaults(): void
    {
        $this->load(Permissions::defaults());
        $this->dispatch('toast', message: 'Default ticks filled in. Press Save to use them.', type: 'warning');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasRole(User::ADMIN), 403);
        $byRole = [];
        foreach (Permissions::EDITABLE as $role) {
            $byRole[$role] = array_map(fn ($k) => str_replace('__', '.', $k), array_keys(array_filter($this->ticks[$role] ?? [], fn ($v) => (bool) $v)));
        }
        Permissions::save($byRole);
        Audit::log('Users', 'UPDATE', 'roles', collect($byRole)->map(fn ($p, $r) => User::ROLES[$r].': '.count($p).' permissions')->implode(', '));
        $this->dispatch('toast', message: 'Permissions saved. They apply straight away.');
    }

    public static function slot(string $perm): string
    {
        return str_replace('.', '__', $perm);
    }

    public function render()
    {
        $groups = collect(Permissions::ALL)->map(fn ($v, $k) => ['key' => $k, 'label' => $v[0], 'group' => $v[1]])->groupBy('group');
        $counts = User::selectRaw('role, count(*) as n')->where('is_active', true)->groupBy('role')->pluck('n', 'role');

        return view('livewire.users.roles', ['groups' => $groups, 'counts' => $counts]);
    }
}
