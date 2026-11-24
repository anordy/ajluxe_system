<?php

namespace App\Livewire\Returns;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\StockReturn;
use App\Services\ReturnManager;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Returns')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    /** deep links: ?invoice=INV-2026-0001 or ?po=PO-2026-0001 open the form */
    #[Url(as: 'invoice', except: '')]
    public string $invoiceNo = '';

    #[Url(as: 'po', except: '')]
    public string $poNo = '';

    public bool $showForm = false;
    public string $formType = 'customer';
    public string $docNo = '';
    public ?int $docId = null;
    public array $qty = [];
    public string $reason = '';
    public string $refundMode = 'Cash';

    public function mount(): void
    {
        if ($this->invoiceNo !== '') {
            $this->open('customer', $this->invoiceNo);
        } elseif ($this->poNo !== '') {
            $this->open('supplier', $this->poNo);
        }
    }

    public function updating($field): void
    {
        if (in_array($field, ['search', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function open(string $type, string $docNo = ''): void
    {
        $this->resetErrorBag();
        $this->formType = $type === 'supplier' ? 'supplier' : 'customer';
        $this->docNo = $docNo;
        $this->docId = null;
        $this->qty = [];
        $this->reason = '';
        $this->refundMode = 'Cash';
        $this->showForm = true;
        if ($docNo !== '') {
            $this->findDoc();
        }
    }

    public function findDoc(): void
    {
        $this->resetErrorBag('docNo');
        $no = strtoupper(trim($this->docNo));
        $this->docId = null;
        $this->qty = [];
        if ($no === '') {
            return;
        }
        if ($this->formType === 'customer') {
            $doc = Invoice::where('number', $no)->first();
            if (! $doc) {
                $this->addError('docNo', "No invoice {$no} found.");
            } elseif ($doc->status === Invoice::CANCELLED) {
                $this->addError('docNo', 'That invoice is cancelled.');
            } else {
                $this->docId = $doc->id;
            }
        } else {
            $doc = PurchaseOrder::where('number', $no)->first();
            if (! $doc) {
                $this->addError('docNo', "No purchase order {$no} found.");
            } elseif (! in_array($doc->status, [PurchaseOrder::PARTIAL, PurchaseOrder::RECEIVED], true)) {
                $this->addError('docNo', 'Nothing has been received on that purchase order.');
            } else {
                $this->docId = $doc->id;
            }
        }
        $this->docNo = $no;
    }

    public function save(ReturnManager $m): void
    {
        $this->validate([
            'docId'      => 'required',
            'qty.*'      => 'nullable|numeric|min:0',
            'reason'     => 'required|string|min:3|max:200',
            'refundMode' => 'required|string|max:40',
        ], ['docId.required' => 'Find the document first.']);

        try {
            $ret = $this->formType === 'customer'
                ? $m->customerReturn(Invoice::findOrFail($this->docId), $this->qty, trim($this->reason), $this->refundMode)
                : $m->supplierReturn(PurchaseOrder::findOrFail($this->docId), $this->qty, trim($this->reason), $this->refundMode);
        } catch (BusinessRuleException $e) {
            $this->addError('qty', $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->invoiceNo = $this->poNo = '';
        $msg = "{$ret->number} saved — ".money($ret->total).' returned.';
        if ((float) $ret->refund_amount > 0) {
            $msg .= $ret->isCustomer() ? ' Refund the customer '.money($ret->refund_amount).'.' : ' The supplier owes you '.money($ret->refund_amount).'.';
        }
        $this->dispatch('toast', message: $msg, type: 'success');
    }

    public function render()
    {
        $m = app(ReturnManager::class);
        $doc = null;
        $rows = collect();
        if ($this->showForm && $this->docId) {
            $doc = $this->formType === 'customer' ? Invoice::with('customer')->find($this->docId) : PurchaseOrder::with('supplier')->find($this->docId);
            $rows = $this->formType === 'customer' ? $m->returnableForInvoice($doc) : $m->returnableForPurchase($doc);
        }

        $term = trim($this->search);
        $list = StockReturn::with(['customer:id,name', 'supplier:id,name', 'invoice:id,number,walk_in_name,customer_id', 'purchaseOrder:id,number', 'creator:id,name'])
            ->withCount('items')
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                ->orWhereHas('invoice', fn ($i) => $i->where('number', 'like', "%{$term}%"))
                ->orWhereHas('purchaseOrder', fn ($p) => $p->where('number', 'like', "%{$term}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%"))))
            ->latest('id')->paginate(25);

        return view('livewire.returns.index', [
            'list'  => $list,
            'doc'   => $doc,
            'rows'  => $rows,
            'modes' => list_options('payment_mode'),
        ]);
    }
}
