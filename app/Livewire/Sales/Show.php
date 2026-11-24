<?php

namespace App\Livewire\Sales;

use App\Models\Invoice;
use App\Models\Order;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One sale / invoice on one page: who bought, exactly what, what was paid and how,
 * returns, and the print buttons. Spare-parts sales (POS and website orders) and garage invoices.
 */
class Show extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        // mechanics only see garage work, not the shop's sales
        abort_if(auth()->user()->hasRole('mechanic') && ! auth()->user()->hasRole('admin', 'manager', 'staff'), 403);
        $this->invoice = $invoice;
    }

    #[On('payment-saved')]
    public function refreshSale(): void
    {
        $this->invoice->refresh();
    }

    public function render()
    {
        $inv = $this->invoice->load([
            'items.part:id,sku,name,brand,unit,oem_no',
            'payments' => fn ($q) => $q->orderBy('date')->orderBy('id'),
            'payments.creator:id,name',
            'customer', 'creator:id,name', 'jobCard.vehicle', 'returns.items',
        ]);
        $returned = [];
        foreach ($inv->returns as $r) {
            foreach ($r->items as $ri) {
                $returned[$ri->part_id] = ($returned[$ri->part_id] ?? 0) + (float) $ri->qty;
            }
        }

        return view('livewire.sales.show', [
            'inv'      => $inv,
            'order'    => Order::where('invoice_id', $inv->id)->first(),
            'returned' => $returned,
            'canManage'=> auth()->user()->hasRole('admin', 'manager'),
            'canPay'   => auth()->user()->hasRole('admin', 'manager', 'staff'),
        ])->title(($inv->isCounter() ? 'Sale ' : 'Invoice ').$inv->number);
    }
}
