<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\StockReturn;

/** Printable A4 pages (open in a new tab, print dialog opens automatically; "?noprint" disables it). */
class PrintController extends Controller
{
    /** Workshop copy: vehicle, complaint, items without prices, sign-off boxes. */
    public function jobCard(JobCard $jobCard)
    {
        $this->allow('admin', 'manager', 'mechanic');
        $jobCard->load(['customer', 'vehicle', 'mechanic', 'items.part', 'items.service']);

        return view('print.job-card', ['job' => $jobCard, 'title' => 'JOB CARD']);
    }

    /** Quotation from the job card's current items — no signatures. */
    public function proforma(JobCard $jobCard)
    {
        $this->allow('admin', 'manager');
        abort_if($jobCard->status === JobCard::CANCELLED, 404);
        $jobCard->load(['customer', 'vehicle', 'items.part', 'items.service']);

        return view('print.invoice', [
            'proforma' => true,
            'title'    => 'PROFORMA INVOICE',
            'number'   => 'PF-'.$jobCard->number,
            'date'     => now(),
            'customer' => $jobCard->customer,
            'customerName' => $jobCard->customer?->name,
            'job'      => $jobCard,
            'lines'    => $jobCard->items,
            'subtotal' => (float) $jobCard->subtotal,
            'discount' => (float) $jobCard->discount,
            'taxRate'  => (float) $jobCard->tax_rate,
            'tax'      => (float) $jobCard->tax_amount,
            'total'    => (float) $jobCard->grand_total,
            'paid'     => (float) $jobCard->payments()->sum('amount'),
        ]);
    }

    /** Tax invoice with signatures. */
    public function invoice(Invoice $invoice)
    {
        $this->allow('admin', 'manager', 'staff');
        $invoice->load(['customer', 'items', 'jobCard.vehicle', 'jobCard.items.service', 'jobCard.items.part', 'payments']);

        // garage invoices print with labour hours / part units from the job card lines
        $lines = $invoice->jobCard ? $invoice->jobCard->items : $invoice->items;
        $taxRate = (float) ($invoice->items->first()->tax_rate ?? 0);

        return view('print.invoice', [
            'proforma' => false,
            'title'    => $invoice->status === Invoice::CANCELLED ? ($invoice->isCounter() ? 'SALE (VOIDED)' : 'INVOICE (CANCELLED)') : 'TAX INVOICE',
            'number'   => $invoice->number,
            'date'     => $invoice->date,
            'customer' => $invoice->customer,
            'customerName' => $invoice->customerName(),
            'job'      => $invoice->jobCard,
            'invoice'  => $invoice,
            'lines'    => $lines,
            'subtotal' => (float) $invoice->subtotal,
            'discount' => (float) $invoice->discount,
            'taxRate'  => $taxRate,
            'tax'      => (float) $invoice->tax_amount,
            'total'    => (float) $invoice->grand_total,
            'credited' => (float) $invoice->credited,
            'paid'     => (float) $invoice->paid,
        ]);
    }

    /** 80mm thermal receipt for counter sales. */
    public function thermal(Invoice $invoice)
    {
        $this->allow('admin', 'manager', 'staff');
        $invoice->load(['customer', 'items', 'payments', 'creator']);

        return view('print.thermal', ['inv' => $invoice]);
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->allow('admin', 'manager');
        $purchaseOrder->load(['supplier', 'items.part']);

        return view('print.purchase-order', ['po' => $purchaseOrder, 'title' => 'PURCHASE ORDER']);
    }

    public function stockReturn(StockReturn $stockReturn)
    {
        $this->allow('admin', 'manager');
        $stockReturn->load(['items.part', 'invoice', 'purchaseOrder', 'customer', 'supplier', 'creator']);

        return view('print.return', ['r' => $stockReturn, 'title' => $stockReturn->isCustomer() ? 'CREDIT NOTE' : 'DEBIT NOTE']);
    }

    public function receipt(Payment $payment)
    {
        $this->allow('admin', 'manager', 'staff');
        $payment->load(['customer', 'invoice', 'jobCard.vehicle', 'creator']);

        return view('print.receipt', ['p' => $payment, 'title' => 'PAYMENT RECEIPT']);
    }

    private function allow(string ...$roles): void
    {
        abort_unless(auth()->user()?->hasRole(...$roles), 403);
    }
}
