<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Models\Payment;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Invoices & Payments')]
class Index extends Component
{
    use WithPagination;

    /** invoices | payments */
    #[Url(except: 'invoices')]
    public string $view = 'invoices';

    #[Url(except: '')]
    public string $search = '';

    /** '' | unpaid (unpaid + partial) | paid | cancelled */
    #[Url(except: '')]
    public string $status = '';

    /** '' | garage | counter */
    #[Url(except: '')]
    public string $kind = '';

    #[Url(except: '')]
    public string $from = '';

    public bool $showVoid = false;
    public ?int $voidId = null;
    public string $voidReason = '';
    public string $voidRefundMode = 'Cash';

    #[Url(except: '')]
    public string $to = '';

    public function updating($field): void
    {
        if (in_array($field, ['view', 'search', 'status', 'kind', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    #[On('payment-saved')]
    public function refreshList(): void {}

    public function canVoid(): bool
    {
        return auth()->user()->allows('sales.manage');
    }

    public function openVoid(int $id): void
    {
        abort_unless($this->canVoid(), 403);
        $this->resetErrorBag();
        $this->voidId = $id;
        $this->voidReason = '';
        $this->voidRefundMode = 'Cash';
        $this->showVoid = true;
    }

    public function confirmVoid(\App\Services\SalesManager $sales): void
    {
        abort_unless($this->canVoid(), 403);
        $this->validate(['voidReason' => 'required|string|min:3|max:200'], [], ['voidReason' => 'reason']);
        $inv = Invoice::findOrFail($this->voidId);
        try {
            $sales->voidCounterSale($inv, trim($this->voidReason), $this->voidRefundMode);
        } catch (\App\Exceptions\BusinessRuleException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }
        $this->showVoid = false;
        $this->dispatch('toast', message: "Sale {$inv->number} voided. Stock returned.", type: 'success');
    }

    public function render()
    {
        $term = trim($this->search);

        if ($this->view === 'payments') {
            $rows = Payment::with(['customer:id,name', 'invoice:id,number', 'jobCard:id,number', 'creator:id,name'])
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                    ->orWhereHas('invoice', fn ($i) => $i->where('number', 'like', "%{$term}%"))))
                ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to))
                ->latest('id');
            $total = (clone $rows)->sum('amount');
        } else {
            $rows = Invoice::with(['customer:id,name,phone', 'jobCard:id,number,vehicle_id', 'jobCard.vehicle:id,reg_no'])
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                    ->orWhere('walk_in_name', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                    ->orWhereHas('jobCard', fn ($j) => $j->where('number', 'like', "%{$term}%")
                        ->orWhereHas('vehicle', fn ($v) => $v->where('reg_no', 'like', "%{$term}%")))))
                ->when(in_array($this->kind, ['garage', 'counter'], true), fn ($q) => $q->where('type', $this->kind))
                ->when($this->status === 'unpaid', fn ($q) => $q->whereIn('status', [Invoice::UNPAID, Invoice::PARTIAL]))
                ->when(in_array($this->status, [Invoice::PAID, Invoice::CANCELLED], true), fn ($q) => $q->where('status', $this->status))
                ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to))
                ->latest('id');
            $total = (clone $rows)->where('status', '!=', Invoice::CANCELLED)->sum('grand_total');
        }

        return view('livewire.invoices.index', [
            'rows'        => $rows->paginate(25),
            'total'       => (float) $total,
            'outstanding' => (float) Invoice::active()->sum('balance'),
            'todayIn'     => (float) Payment::whereDate('date', today())->sum('amount'),
            'voidInvoice' => $this->showVoid && $this->voidId ? Invoice::find($this->voidId) : null,
            'modes'       => list_options('payment_mode'),
            'monthIn'     => (float) Payment::whereDate('date', '>=', now()->startOfMonth()->toDateString())->whereDate('date', '<=', today()->toDateString())->sum('amount'),
        ]);
    }
}
