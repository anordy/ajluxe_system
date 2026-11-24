<?php

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\Supplier;
use App\Support\Audit;
use App\Support\Numbering;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Suppliers')]
class Index extends Component
{
    use ManagesRecords, WithPagination;

    protected array $manageRoles = ['admin', 'manager'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    public function updating($field): void
    {
        if (in_array($field, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    private static function blank(): array
    {
        return ['name' => '', 'contact_person' => '', 'phone' => '', 'email' => '', 'address' => '', 'tin' => '',
            'opening_balance' => '', 'is_active' => true];
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
        $s = Supplier::findOrFail($id);
        $this->editingId = $s->id;
        $this->form = array_merge(self::blank(), array_map(fn ($v) => $v ?? '', $s->only(array_keys(self::blank()))));
        $this->form['is_active'] = (bool) $s->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'form.name'            => 'required|string|max:150',
            'form.contact_person'  => 'nullable|string|max:120',
            'form.phone'           => 'nullable|string|max:30',
            'form.email'           => 'nullable|email|max:120',
            'form.address'         => 'nullable|string|max:255',
            'form.tin'             => 'nullable|string|max:40',
            'form.opening_balance' => 'nullable|numeric',
            'form.is_active'       => 'boolean',
        ])['form'];
        $data['opening_balance'] = (float) ($data['opening_balance'] ?: 0);

        if ($this->editingId) {
            $s = Supplier::findOrFail($this->editingId);
            $s->update($data);
            Audit::log('Suppliers', 'UPDATE', $s->code, $s->name);
            $this->toast('Supplier updated.');
        } else {
            $s = Supplier::create($data + ['code' => Numbering::code('supplier', 'SUP')]);
            Audit::log('Suppliers', 'CREATE', $s->code, $s->name);
            $this->toast("Supplier {$s->code} added.");
        }
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $s = Supplier::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        Audit::log('Suppliers', $s->is_active ? 'ACTIVATE' : 'DEACTIVATE', $s->code);
        $this->toast($s->name.' is now '.($s->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $s = Supplier::findOrFail($id);
        if ($reason = $s->deleteBlocker()) {
            $this->toast($reason, 'error');

            return;
        }
        $s->delete();
        Audit::log('Suppliers', 'DELETE', $s->code, $s->name);
        $this->toast('Supplier deleted.');
    }

    public function render()
    {
        return view('livewire.suppliers.index', [
            'suppliers' => Supplier::search($this->search)
                ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
                ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
                ->orderBy('name')->paginate(20),
        ]);
    }
}
