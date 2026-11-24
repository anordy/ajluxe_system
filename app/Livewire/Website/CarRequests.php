<?php

namespace App\Livewire\Website;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\CarModel;
use App\Models\CarRequest;
use App\Support\Audit;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** "Can't find your car?" requests from the website: call the customer back, add the car to the car list. */
#[Title('Car Requests')]
class CarRequests extends Component
{
    use ManagesRecords, WithPagination;

    protected array $manageRoles = ['admin', 'manager', 'staff'];

    #[Url(except: 'new')]
    public string $tab = 'new';

    #[Url(except: '')]
    public string $search = '';

    public ?int $viewId = null;
    public bool $showView = false;
    public string $note = '';

    public function updating($f): void
    {
        if (in_array($f, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $id): void
    {
        $r = CarRequest::findOrFail($id);
        $this->viewId = $r->id;
        $this->note = (string) $r->staff_note;
        $this->showView = true;
    }

    public function setStatus(string $status): void
    {
        $this->authorizeManage();
        abort_unless(isset(CarRequest::STATUSES[$status]), 422);
        $r = CarRequest::findOrFail($this->viewId);
        $r->update(['status' => $status, 'staff_note' => trim($this->note) ?: null, 'handled_by' => auth()->id(), 'handled_at' => now()]);
        Audit::log('Website', 'CAR_REQUEST_'.strtoupper($status), $r->car());
        $this->toast('Request marked '.strtolower(CarRequest::STATUSES[$status]).'.');
        $this->showView = false;
    }

    /** Put the car in the website's car list and mark the request done. */
    public function addToList(): void
    {
        $this->authorizeManage();
        $r = CarRequest::findOrFail($this->viewId);
        $make = ucwords(strtolower(trim($r->make)));
        $car = CarModel::firstOrCreate(
            ['make' => $make, 'model' => trim($r->model)],
            ['year_from' => $r->year, 'is_active' => true],
        );
        if (! $car->is_active) {
            $car->update(['is_active' => true]);
        }
        $r->update(['status' => 'added', 'staff_note' => trim($this->note) ?: null, 'handled_by' => auth()->id(), 'handled_at' => now()]);
        Audit::log('Website', 'CAR_ADDED', $car->make.' '.$car->model);
        $this->toast($car->make.' '.$car->model.' is now in the car list on the website.');
        $this->showView = false;
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasRole('admin', 'manager'), 403);
        $r = CarRequest::findOrFail($id);
        $r->delete();
        Audit::log('Website', 'CAR_REQUEST_DELETE', $r->car());
        $this->showView = false;
        $this->toast('Request deleted.');
    }

    public function render()
    {
        $q = CarRequest::query()
            ->when($this->tab !== 'all', fn ($q) => $q->where('status', $this->tab))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('make', 'like', "%{$this->search}%")
                ->orWhere('model', 'like', "%{$this->search}%")->orWhere('vin', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")->orWhere('parts', 'like', "%{$this->search}%")))
            ->latest();

        return view('livewire.website.car-requests', [
            'requests' => $q->paginate(20),
            'counts'   => CarRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'view'     => $this->viewId ? CarRequest::with('handler:id,name', 'customer:id,name')->find($this->viewId) : null,
        ]);
    }
}
