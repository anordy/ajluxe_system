<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Support\Audit;
use Livewire\Attributes\On;
use Livewire\Component;

/** Customer profile: details, balance, vehicles, job cards and invoices. */
class Show extends Component
{
    use ManagesRecords;

    protected array $manageRoles = ['admin', 'manager', 'staff'];

    public Customer $customer;

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function edit(): void
    {
        $this->authorizeManage();
        $c = $this->customer;
        $this->editingId = $c->id;
        $this->form = array_merge(Index::blank(), array_map(fn ($v) => $v ?? '', $c->only(array_keys(Index::blank()))));
        $this->form['is_active'] = (bool) $c->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = $this->validate(Index::rules($this->customer->id))['form'];
        $data['credit_limit'] = (float) ($data['credit_limit'] ?: 0);
        $data['opening_balance'] = (float) ($data['opening_balance'] ?: 0);
        $this->customer->update($data);
        Audit::log('Customers', 'UPDATE', $this->customer->code, $this->customer->name);
        $this->showForm = false;
        $this->toast('Customer updated.');
    }

    public function deleteVehicle(int $id): void
    {
        $this->authorizeManage();
        $v = Vehicle::where('customer_id', $this->customer->id)->findOrFail($id);
        if ($reason = $v->deleteBlocker()) {
            $this->toast($reason, 'error');

            return;
        }
        $v->delete();
        Audit::log('Vehicles', 'DELETE', $v->reg_no);
        $this->toast("Vehicle {$v->reg_no} removed.");
    }

    #[On('vehicle-saved')]
    #[On('payment-saved')]
    public function refreshVehicles(): void
    {
        $this->customer->refresh();
    }

    public function render()
    {
        return view('livewire.customers.show', [
            'vehicles' => $this->customer->vehicles()->orderBy('reg_no')->get(),
            'balance'  => $this->customer->balance(),
            'types'    => list_options('customer_type'),
            'jobs'     => $this->customer->jobCards()->with('vehicle:id,reg_no')->latest('id')->get(),
            'invoices' => $this->customer->invoices()->latest('id')->get(),
            'canJobs'  => auth()->user()->hasRole('admin', 'manager'),
        ])->title($this->customer->name);
    }
}
