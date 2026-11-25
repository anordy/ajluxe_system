<?php

namespace App\Livewire\Mechanics;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\Mechanic;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mechanics')]
class Index extends Component
{
    use ManagesRecords;

    protected string $managePermission = 'mechanics';

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    private static function blank(): array
    {
        return ['name' => '', 'phone' => '', 'specialization' => '', 'user_id' => '', 'is_active' => true];
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->editingId = null;
        $this->form = self::blank();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $m = Mechanic::findOrFail($id);
        $this->editingId = $m->id;
        $this->form = array_merge(self::blank(), array_map(fn ($v) => $v ?? '', $m->only(array_keys(self::blank()))));
        $this->form['is_active'] = (bool) $m->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Picking a login fills in the name/phone if they are still empty. */
    public function updatedFormUserId($value): void
    {
        if ($value && ($u = User::find($value))) {
            $this->form['name'] = $this->form['name'] ?: $u->name;
            $this->form['phone'] = $this->form['phone'] ?: (string) $u->phone;
        }
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'form.name'           => 'required|string|max:120',
            'form.phone'          => 'nullable|string|max:30',
            'form.specialization' => 'nullable|string|max:80',
            'form.user_id'        => ['nullable', 'exists:users,id',
                Rule::unique('mechanics', 'user_id')->ignore($this->editingId)],
            'form.is_active'      => 'boolean',
        ], ['form.user_id.unique' => 'This login is already linked to another mechanic.'])['form'];
        $data['user_id'] = $data['user_id'] ?: null;

        if ($this->editingId) {
            $m = Mechanic::findOrFail($this->editingId);
            $m->update($data);
            Audit::log('Mechanics', 'UPDATE', $m->name);
            $this->toast('Mechanic updated.');
        } else {
            $m = Mechanic::create($data);
            Audit::log('Mechanics', 'CREATE', $m->name);
            $this->toast('Mechanic added.');
        }
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $m = Mechanic::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);
        Audit::log('Mechanics', $m->is_active ? 'ACTIVATE' : 'DEACTIVATE', $m->name);
        $this->toast($m->name.' is now '.($m->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $m = Mechanic::findOrFail($id);
        if ($reason = $m->deleteBlocker()) {
            $this->toast($reason, 'error');

            return;
        }
        $m->delete();
        Audit::log('Mechanics', 'DELETE', $m->name);
        $this->toast('Mechanic removed.');
    }

    public function render()
    {
        return view('livewire.mechanics.index', [
            'mechanics' => Mechanic::with('user:id,username,is_active')->orderByDesc('is_active')->orderBy('name')->get(),
            'logins'    => User::where('role', User::MECHANIC)->orderBy('name')->get(['id', 'name', 'username']),
            'specs'     => list_options('specialization'),
        ]);
    }
}
