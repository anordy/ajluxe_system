<?php

namespace App\Livewire\Vehicles;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\Vehicle;
use App\Support\Audit;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Vehicles')]
class Index extends Component
{
    use ManagesRecords, WithPagination;

    protected array $manageRoles = ['admin', 'manager', 'staff'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $make = '';

    public function updating($field): void
    {
        if (in_array($field, ['search', 'make'], true)) {
            $this->resetPage();
        }
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $v = Vehicle::findOrFail($id);
        if ($reason = $v->deleteBlocker()) {
            $this->toast($reason, 'error');

            return;
        }
        $v->delete();
        Audit::log('Vehicles', 'DELETE', $v->reg_no);
        $this->toast("Vehicle {$v->reg_no} removed.");
    }

    #[On('vehicle-saved')]
    public function refreshList(): void {}

    public function render()
    {
        return view('livewire.vehicles.index', [
            'vehicles' => Vehicle::with('customer:id,name,phone,code')
                ->search($this->search)
                ->when($this->make, fn ($q) => $q->where('make', $this->make))
                ->orderBy('reg_no')
                ->paginate(20),
            'makes' => list_options('vehicle_make'),
        ]);
    }
}
