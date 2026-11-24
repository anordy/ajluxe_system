<?php

namespace App\Livewire\Vehicles;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reusable add/edit vehicle dialog. Open it from anywhere with:
 *   $dispatch('open-vehicle-form', { customerId: 5 })   // new vehicle for a customer
 *   $dispatch('open-vehicle-form', { id: 12 })          // edit
 * Emits 'vehicle-saved' when done.
 */
class Form extends Component
{
    public bool $show = false;
    public ?int $vehicleId = null;
    public bool $lockCustomer = false;

    public ?int $customer_id = null;
    public string $customerSearch = '';
    public string $customerName = '';

    public array $form = [];

    #[On('open-vehicle-form')]
    public function open(?int $id = null, ?int $customerId = null): void
    {
        abort_unless(auth()->user()->hasRole('admin', 'manager', 'staff'), 403);
        $this->resetValidation();
        $this->reset(['customerSearch']);
        $this->vehicleId = $id;
        $this->form = ['reg_no' => '', 'make' => '', 'model' => '', 'year' => '', 'fuel_type' => '', 'color' => '',
            'vin' => '', 'engine_no' => '', 'odometer' => '', 'notes' => ''];

        if ($id) {
            $v = Vehicle::findOrFail($id);
            $this->form = array_merge($this->form, array_map(fn ($x) => $x ?? '', $v->only(array_keys($this->form))));
            $customerId = $v->customer_id;
        }
        $this->lockCustomer = (bool) $customerId && ! $id;
        $this->pickCustomer($customerId);
        $this->show = true;
    }

    public function pickCustomer(?int $id): void
    {
        $this->customer_id = $id;
        $this->customerName = $id ? (string) Customer::withTrashed()->find($id)?->name : '';
        $this->customerSearch = '';
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasRole('admin', 'manager', 'staff'), 403);
        $this->form['reg_no'] = Vehicle::normalizeReg((string) $this->form['reg_no']);

        $this->validate([
            'customer_id'    => 'required|exists:customers,id',
            'form.reg_no'    => ['required', 'string', 'max:30', Rule::unique('vehicles', 'reg_no')->ignore($this->vehicleId)],
            'form.make'      => 'nullable|string|max:60',
            'form.model'     => 'nullable|string|max:60',
            'form.year'      => 'nullable|integer|min:1950|max:'.(date('Y') + 1),
            'form.fuel_type' => 'nullable|string|max:20',
            'form.color'     => 'nullable|string|max:30',
            'form.vin'       => 'nullable|string|max:40',
            'form.engine_no' => 'nullable|string|max:40',
            'form.odometer'  => 'nullable|integer|min:0',
            'form.notes'     => 'nullable|string|max:1000',
        ], [
            'customer_id.required' => 'Choose the owner of this vehicle.',
            'form.reg_no.unique'   => 'This registration number is already in the system.',
        ]);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['customer_id'] = $this->customer_id;

        if ($this->vehicleId) {
            $v = Vehicle::findOrFail($this->vehicleId);
            $v->update($data);
            Audit::log('Vehicles', 'UPDATE', $v->reg_no);
            $msg = "Vehicle {$v->reg_no} updated.";
        } else {
            $v = Vehicle::create($data);
            Audit::log('Vehicles', 'CREATE', $v->reg_no, 'Owner: '.$this->customerName);
            $msg = "Vehicle {$v->reg_no} added.";
        }

        $this->show = false;
        $this->dispatch('vehicle-saved', id: $v->id);
        $this->dispatch('toast', message: $msg, type: 'success');
    }

    public function render()
    {
        $matches = strlen(trim($this->customerSearch)) >= 2
            ? Customer::active()->search($this->customerSearch)->orderBy('name')->limit(8)->get(['id', 'name', 'phone', 'code'])
            : collect();

        return view('livewire.vehicles.form', [
            'matches' => $matches,
            'makes'   => list_options('vehicle_make'),
            'fuels'   => list_options('fuel_type'),
        ]);
    }
}
