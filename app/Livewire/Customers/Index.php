<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\Customer;
use App\Support\Audit;
use App\Support\Numbering;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Customers')]
class Index extends Component
{
    use ManagesRecords, WithPagination;

    protected array $manageRoles = ['admin', 'manager', 'staff'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    public function updating($field): void
    {
        if (in_array($field, ['search', 'type', 'status'], true)) {
            $this->resetPage();
        }
    }

    #[On('open-customer-form')]
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
        $c = Customer::findOrFail($id);
        $this->editingId = $c->id;
        $this->form = array_merge(self::blank(), array_map(fn ($v) => $v ?? '', $c->only(array_keys(self::blank()))));
        $this->form['is_active'] = (bool) $c->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = $this->validate(self::rules($this->editingId))['form'];
        $data['credit_limit'] = (float) ($data['credit_limit'] ?: 0);
        $data['opening_balance'] = (float) ($data['opening_balance'] ?: 0);

        if ($this->editingId) {
            $c = Customer::findOrFail($this->editingId);
            $c->update($data);
            Audit::log('Customers', 'UPDATE', $c->code, $c->name);
            $this->toast('Customer updated.');
        } else {
            $c = Customer::create($data + ['code' => Numbering::code('customer', 'CUS')]);
            Audit::log('Customers', 'CREATE', $c->code, $c->name);
            $this->toast("Customer {$c->code} added.");
        }
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $c = Customer::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
        Audit::log('Customers', $c->is_active ? 'ACTIVATE' : 'DEACTIVATE', $c->code, $c->name);
        $this->toast($c->name.' is now '.($c->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $c = Customer::findOrFail($id);
        if ($reason = $c->deleteBlocker()) {
            $this->toast($reason, 'error');

            return;
        }
        $c->delete();
        Audit::log('Customers', 'DELETE', $c->code, $c->name);
        $this->toast('Customer deleted.');
    }

    public static function blank(): array
    {
        return [
            'name' => '', 'type' => 'Individual', 'phone' => '', 'alt_phone' => '', 'email' => '', 'address' => '',
            'tin' => '', 'credit_limit' => '', 'opening_balance' => '', 'notes' => '', 'is_active' => true,
        ];
    }

    public static function rules(?int $ignoreId = null): array
    {
        return [
            'form.name'            => 'required|string|max:150',
            'form.type'            => 'required|string|max:30',
            'form.phone'           => 'nullable|string|max:30',
            'form.alt_phone'       => 'nullable|string|max:30',
            'form.email'           => 'nullable|email|max:120',
            'form.address'         => 'nullable|string|max:255',
            'form.tin'             => 'nullable|string|max:40',
            'form.credit_limit'    => 'nullable|numeric|min:0',
            'form.opening_balance' => 'nullable|numeric',
            'form.notes'           => 'nullable|string|max:1000',
            'form.is_active'       => 'boolean',
        ];
    }

    public function render()
    {
        $customers = Customer::query()
            ->withCount('vehicles')
            ->search($this->search)
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.customers.index', [
            'customers' => $customers,
            'types'     => list_options('customer_type'),
        ]);
    }
}
