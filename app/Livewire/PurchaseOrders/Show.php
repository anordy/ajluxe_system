<?php

namespace App\Livewire\PurchaseOrders;

use App\Exceptions\BusinessRuleException;
use App\Models\PurchaseOrder;
use App\Services\PurchaseManager;
use Livewire\Component;

class Show extends Component
{
    public PurchaseOrder $po;

    public bool $showReceive = false;
    public array $receive = [];
    public string $supplierInvoiceNo = '';

    public bool $showPay = false;
    public string $payAmount = '';
    public string $payMode = 'Bank Transfer';
    public string $payReference = '';

    public bool $showCancel = false;
    public string $cancelReason = '';

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->po = $purchaseOrder;
    }

    public function markOrdered(PurchaseManager $m): void
    {
        $this->attempt(fn () => $m->markOrdered($this->po), 'Marked as ordered.');
    }

    public function openReceive(): void
    {
        $this->resetErrorBag();
        $this->receive = $this->po->items()->get()->mapWithKeys(fn ($i) => [$i->id => (string) $i->remaining()])->all();
        $this->supplierInvoiceNo = (string) $this->po->supplier_invoice_no;
        $this->showReceive = true;
    }

    public function saveReceive(PurchaseManager $m): void
    {
        $this->validate(['receive.*' => 'nullable|numeric|min:0', 'supplierInvoiceNo' => 'nullable|string|max:60']);
        if ($this->attempt(fn () => $m->receive($this->po, $this->receive, trim($this->supplierInvoiceNo)), 'Goods received into stock.')) {
            $this->showReceive = false;
        }
    }

    public function openPay(): void
    {
        $this->resetErrorBag();
        $this->payAmount = (string) (float) $this->po->balance;
        $this->payReference = '';
        $modes = list_options('payment_mode');
        if (! in_array($this->payMode, $modes, true)) {
            $this->payMode = $modes[0] ?? 'Cash';
        }
        $this->showPay = true;
    }

    public function savePay(PurchaseManager $m): void
    {
        $this->validate(['payAmount' => 'required|numeric|min:1', 'payMode' => 'required|string|max:40', 'payReference' => 'nullable|string|max:80']);
        try {
            $p = $m->recordPayment($this->po, (float) $this->payAmount, $this->payMode, strtoupper(trim($this->payReference)));
        } catch (BusinessRuleException $e) {
            $this->addError('payAmount', $e->getMessage());

            return;
        }
        $this->showPay = false;
        $this->po->refresh();
        $this->dispatch('toast', message: "Payment {$p->number} recorded.", type: 'success');
    }

    public function confirmCancel(PurchaseManager $m): void
    {
        $this->validate(['cancelReason' => 'required|string|min:3|max:200'], [], ['cancelReason' => 'reason']);
        if ($this->attempt(fn () => $m->cancel($this->po, trim($this->cancelReason)), 'Purchase order cancelled.')) {
            $this->showCancel = false;
        }
    }

    private function attempt(callable $fn, string $ok): bool
    {
        try {
            $fn();
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            $this->po->refresh();

            return false;
        }
        $this->po->refresh();
        $this->dispatch('toast', message: $ok, type: 'success');

        return true;
    }

    public function render()
    {
        $this->po->load(['supplier', 'items.part', 'payments.creator:id,name', 'returns', 'creator:id,name']);

        return view('livewire.purchase-orders.show', ['modes' => list_options('payment_mode')])->title($this->po->number);
    }
}
