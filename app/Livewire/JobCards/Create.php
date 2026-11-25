<?php

namespace App\Livewire\JobCards;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Vehicle;
use App\Services\JobCardManager;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('New Job Card')]
class Create extends Component
{
    #[Url(as: 'customer')]
    public ?int $customer_id = null;

    #[Url(as: 'vehicle')]
    public ?int $vehicle_id = null;

    public string $customerSearch = '';
    public ?int $mechanic_id = null;
    public string $priority = 'Normal';
    public string $odometer_in = '';
    public string $fuel_level = '';
    public string $complaints = '';
    public string $inspection_notes = '';
    public string $promised_at = '';
    public string $advance = '';
    public string $advance_mode = 'Cash';
    public string $advance_ref = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->allows('job_cards.manage'), 403);
        if ($this->vehicle_id && ($v = Vehicle::find($this->vehicle_id))) {
            $this->customer_id = $v->customer_id;
            $this->odometer_in = (string) ($v->odometer ?? '');
        }
    }

    public function pickCustomer(?int $id): void
    {
        $this->customer_id = $id;
        $this->vehicle_id = null;
        $this->customerSearch = '';
        if ($id && ($only = Vehicle::where('customer_id', $id)->get())->count() === 1) {
            $this->pickVehicle($only->first()->id);
        }
    }

    /** Picked from a plate-number match: sets the owner and the vehicle together. */
    public function pickPlate(int $vehicleId): void
    {
        $v = Vehicle::findOrFail($vehicleId);
        $this->customer_id = $v->customer_id;
        $this->customerSearch = '';
        $this->pickVehicle($v->id);
    }

    public function pickVehicle(?int $id): void
    {
        $this->vehicle_id = $id;
        $v = $id ? Vehicle::find($id) : null;
        $this->odometer_in = (string) ($v?->odometer ?? '');
    }

    #[On('vehicle-saved')]
    public function vehicleAdded(int $id): void
    {
        $this->pickVehicle($id);
    }

    public function save(JobCardManager $manager)
    {
        abort_unless(auth()->user()->allows('job_cards.manage'), 403);
        $this->validate([
            'customer_id'      => 'required|exists:customers,id',
            'vehicle_id'       => 'required|exists:vehicles,id',
            'mechanic_id'      => 'nullable|exists:mechanics,id',
            'priority'         => 'required|string|max:20',
            'odometer_in'      => 'nullable|integer|min:0',
            'fuel_level'       => 'nullable|string|max:10',
            'complaints'       => 'required|string|max:3000',
            'inspection_notes' => 'nullable|string|max:3000',
            'promised_at'      => 'nullable|date',
            'advance'          => 'nullable|numeric|min:0',
            'advance_mode'     => 'required_with:advance|string|max:40',
            'advance_ref'      => 'nullable|string|max:80',
        ], [
            'customer_id.required' => 'Choose the customer.',
            'vehicle_id.required'  => 'Choose the vehicle (or add one).',
            'complaints.required'  => 'Write what the customer reported / work requested.',
        ]);

        $vehicle = Vehicle::findOrFail($this->vehicle_id);
        if ($vehicle->customer_id !== $this->customer_id) {
            $this->addError('vehicle_id', 'This vehicle belongs to another customer.');

            return;
        }

        try {
            $job = $manager->create([
                'customer_id'      => $this->customer_id,
                'vehicle_id'       => $this->vehicle_id,
                'mechanic_id'      => $this->mechanic_id ?: null,
                'priority'         => $this->priority,
                'odometer_in'      => $this->odometer_in !== '' ? (int) $this->odometer_in : null,
                'fuel_level'       => $this->fuel_level ?: null,
                'complaints'       => trim($this->complaints),
                'inspection_notes' => trim($this->inspection_notes) ?: null,
                'promised_at'      => $this->promised_at ?: null,
            ], (float) ($this->advance ?: 0), $this->advance_mode, $this->advance_ref);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        // keep the vehicle's odometer up to date
        if ($this->odometer_in !== '' && (int) $this->odometer_in > (int) $vehicle->odometer) {
            $vehicle->update(['odometer' => (int) $this->odometer_in]);
        }

        session()->flash('toast', ['message' => "Job card {$job->number} opened.", 'type' => 'success']);

        return $this->redirect(route('job-cards.show', $job), navigate: true);
    }

    public function render()
    {
        return view('livewire.job-cards.create', [
            'customer'   => $this->customer_id ? Customer::find($this->customer_id) : null,
            'vehicles'   => $this->customer_id ? Vehicle::where('customer_id', $this->customer_id)->orderBy('reg_no')->get() : collect(),
            'matches'    => strlen(trim($this->customerSearch)) >= 2
                ? Customer::active()->search($this->customerSearch)->withCount('vehicles')->orderBy('name')->limit(8)->get()
                : collect(),
            'plates'     => strlen(trim($this->customerSearch)) >= 2
                ? Vehicle::with('customer:id,name,phone')
                    ->where(fn ($q) => $q->where('reg_no', 'like', '%'.trim($this->customerSearch).'%')
                        ->orWhereRaw("REPLACE(reg_no, ' ', '') like ?", ['%'.str_replace(' ', '', trim($this->customerSearch)).'%']))
                    ->orderBy('reg_no')->limit(6)->get()
                : collect(),
            'mechanics'  => Mechanic::active()->orderBy('name')->get(),
            'priorities' => list_options('priority'),
            'fuelLevels' => list_options('fuel_level'),
            'modes'      => list_options('payment_mode'),
        ]);
    }
}
