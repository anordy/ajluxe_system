<?php

namespace App\Livewire\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Purchase Orders')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '' | open (draft/ordered/partial) | a status */
    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $supplier = '';

    public function updating($field): void
    {
        if (in_array($field, ['search', 'status', 'supplier'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $rows = PurchaseOrder::with('supplier:id,name')->withCount('items')
            ->search($this->search)
            ->when($this->supplier, fn ($q) => $q->where('supplier_id', $this->supplier))
            ->when($this->status === 'open', fn ($q) => $q->whereIn('status', [PurchaseOrder::DRAFT, PurchaseOrder::ORDERED, PurchaseOrder::PARTIAL]))
            ->when($this->status === 'unpaid', fn ($q) => $q->whereIn('status', PurchaseOrder::OWING)->where('balance', '>', 0))
            ->when(isset(PurchaseOrder::LABELS[$this->status]), fn ($q) => $q->where('status', $this->status))
            ->latest('id')->paginate(25);

        return view('livewire.purchase-orders.index', [
            'rows'      => $rows,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'owed'      => (float) PurchaseOrder::whereIn('status', PurchaseOrder::OWING)->sum('balance'),
            'openCount' => PurchaseOrder::whereIn('status', [PurchaseOrder::ORDERED, PurchaseOrder::PARTIAL])->count(),
        ]);
    }
}
