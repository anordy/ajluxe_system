<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\JobStatusHistory;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StockMovement;
use App\Support\Audit;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;

/**
 * All garage rules live here so every screen behaves the same:
 *  - parts used on a job come out of stock immediately (and go back if removed / job cancelled)
 *  - totals are always recalculated on the server
 *  - a job needs items before "Ready for Delivery", and an invoice before "Delivered"
 *  - once invoiced, items are locked; advances are carried onto the invoice
 */
class JobCardManager
{
    // ------------------------------------------------------------------ create

    public function create(array $data, ?float $advance = null, ?string $advanceMode = null, ?string $advanceRef = null): JobCard
    {
        return DB::transaction(function () use ($data, $advance, $advanceMode, $advanceRef) {
            $job = JobCard::create($data + [
                'number'      => Numbering::next('job_card'),
                'status'      => JobCard::OPEN,
                'received_at' => now(),
                'tax_rate'    => (float) setting('default_tax_rate', 0),
                'created_by'  => auth()->id(),
            ]);
            $this->log($job, null, JobCard::OPEN, 'Job card opened');

            if ($advance && $advance > 0) {
                $this->recordPayment($job, null, $advance, $advanceMode ?: 'Cash', $advanceRef, 'Advance');
            }
            Audit::log('Job Cards', 'CREATE', $job->number, 'Vehicle: '.$job->vehicle?->reg_no);

            return $job;
        });
    }

    // ------------------------------------------------------------------ items

    /**
     * Add a line. $line = [type, service_id?, part_id?, description, qty, rate, cost?]
     */
    public function addItem(JobCard $job, array $line): JobCardItem
    {
        $this->assertEditable($job);

        return DB::transaction(function () use ($job, $line) {
            $qty = round((float) $line['qty'], 2);
            if ($qty <= 0) {
                throw new BusinessRuleException('Quantity must be more than 0.');
            }
            $rate = max(0, round((float) $line['rate'], 2));
            $cost = max(0, round((float) ($line['cost'] ?? 0), 2));

            $stockCost = null;
            if ($line['type'] === 'part') {
                $part = Part::findOrFail($line['part_id']);
                $part->assertPriceAllowed($rate);
                $this->takeStock($job, $part, $qty);
                // garage cost = what the spare-parts shop charges (its selling price); stock cost = buying price
                if (! isset($line['cost'])) {
                    $cost = round($qty * (float) $part->selling_price, 2);
                }
                $stockCost = round($qty * (float) $part->purchase_price, 2);
            }

            $item = $job->items()->create([
                'type'        => $line['type'],
                'service_id'  => $line['type'] === 'service' ? ($line['service_id'] ?? null) : null,
                'part_id'     => $line['type'] === 'part' ? $line['part_id'] : null,
                'description' => trim($line['description']),
                'qty'         => $qty,
                'rate'        => $rate,
                'amount'      => round($qty * $rate, 2),
                'cost'        => $cost,
                'stock_cost'  => $stockCost,
            ]);
            $this->recalc($job);
            Audit::log('Job Cards', 'ADD ITEM', $job->number, $item->description.' × '.$qty);

            return $item;
        });
    }

    public function updateItem(JobCardItem $item, array $changes): JobCardItem
    {
        $job = $item->jobCard;
        $this->assertEditable($job);

        return DB::transaction(function () use ($item, $job, $changes) {
            $qty = round((float) ($changes['qty'] ?? $item->qty), 2);
            if ($qty <= 0) {
                throw new BusinessRuleException('Quantity must be more than 0.');
            }
            $diff = round($qty - (float) $item->qty, 2);
            $rate = max(0, round((float) ($changes['rate'] ?? $item->rate), 2));
            $stockCost = $item->stock_cost;
            if ($item->type === 'part') {
                $part = Part::withTrashed()->findOrFail($item->part_id);
                $part->assertPriceAllowed($rate);
                if ($diff != 0.0) {
                    $diff > 0 ? $this->takeStock($job, $part, $diff) : $this->returnStock($job, $part, -$diff, 'Quantity reduced');
                    $unit = (float) $item->qty > 0 && $item->stock_cost !== null ? (float) $item->stock_cost / (float) $item->qty : (float) $part->purchase_price;
                    $stockCost = round($unit * $qty, 2);
                }
            }
            $item->update([
                'description' => trim($changes['description'] ?? $item->description),
                'qty'         => $qty,
                'rate'        => $rate,
                'amount'      => round($qty * $rate, 2),
                'cost'        => max(0, round((float) ($changes['cost'] ?? $item->cost), 2)),
                'stock_cost'  => $stockCost,
            ]);
            $this->recalc($job);

            return $item;
        });
    }

    public function removeItem(JobCardItem $item): void
    {
        $job = $item->jobCard;
        $this->assertEditable($job);

        DB::transaction(function () use ($item, $job) {
            if ($item->type === 'part' && $item->part_id) {
                $this->returnStock($job, Part::withTrashed()->findOrFail($item->part_id), (float) $item->qty, 'Removed from job');
            }
            $item->delete();
            $this->recalc($job);
            Audit::log('Job Cards', 'REMOVE ITEM', $job->number, $item->description);
        });
    }

    /** Discount and tax are both percentages (0–100). */
    public function setDiscountAndTax(JobCard $job, float $discountRate, float $taxRate): void
    {
        $this->assertEditable($job);
        if ($discountRate < 0 || $discountRate > 100 || $taxRate < 0 || $taxRate > 100) {
            throw new BusinessRuleException('Discount and tax must be between 0% and 100%.');
        }
        $job->forceFill(['discount_rate' => round($discountRate, 2), 'tax_rate' => round($taxRate, 2)])->save();
        $this->recalc($job);
    }

    public function recalc(JobCard $job): void
    {
        $subtotal = round((float) $job->items()->sum('amount'), 2);
        $discount = round($subtotal * (float) $job->discount_rate / 100, 2);
        $taxable = max(0, $subtotal - $discount);
        $tax = round($taxable * (float) $job->tax_rate / 100, 2);
        $job->forceFill([
            'subtotal'    => $subtotal,
            'discount'    => $discount,
            'tax_amount'  => $tax,
            'grand_total' => round($taxable + $tax, 2),
        ])->save();
    }

    // ------------------------------------------------------------------ status

    public function changeStatus(JobCard $job, string $to, ?string $note = null): void
    {
        if ($job->status === $to) {
            return;
        }
        if ($job->isClosed()) {
            throw new BusinessRuleException('This job card is closed and cannot change status.');
        }
        if (! in_array($to, array_merge(JobCard::STATUSES, [JobCard::CANCELLED]), true)) {
            throw new BusinessRuleException('Unknown status.');
        }
        if (in_array($to, [JobCard::READY, JobCard::DELIVERED], true) && ! $job->items()->exists()) {
            throw new BusinessRuleException("Add at least one service or part before moving the job to \"{$to}\".");
        }
        if ($to === JobCard::DELIVERED && ! $job->isInvoiced()) {
            throw new BusinessRuleException('Generate the invoice before marking the vehicle as delivered.');
        }
        if ($to === JobCard::CANCELLED) {
            $this->cancel($job, $note);

            return;
        }

        DB::transaction(function () use ($job, $to, $note) {
            $from = $job->status;
            $job->forceFill(['status' => $to, 'delivered_at' => $to === JobCard::DELIVERED ? now() : $job->delivered_at])->save();
            $this->log($job, $from, $to, $note);
            Audit::log('Job Cards', 'STATUS', $job->number, "{$from} → {$to}");
        });
    }

    private function cancel(JobCard $job, ?string $note): void
    {
        if ($job->isInvoiced()) {
            throw new BusinessRuleException('This job is already invoiced and cannot be cancelled.');
        }
        if ($job->payments()->exists()) {
            throw new BusinessRuleException('This job has an advance payment. Refund it first, then cancel.');
        }
        DB::transaction(function () use ($job, $note) {
            foreach ($job->items()->where('type', 'part')->get() as $item) {
                $this->returnStock($job, Part::withTrashed()->findOrFail($item->part_id), (float) $item->qty, 'Job cancelled');
            }
            $from = $job->status;
            $job->forceFill(['status' => JobCard::CANCELLED])->save();
            $this->log($job, $from, JobCard::CANCELLED, $note ?: 'Job cancelled');
            Audit::log('Job Cards', 'CANCEL', $job->number, $note);
        });
    }

    // ------------------------------------------------------------------ invoice & payments

    public function generateInvoice(JobCard $job): Invoice
    {
        if ($job->status === JobCard::CANCELLED) {
            throw new BusinessRuleException('A cancelled job cannot be invoiced.');
        }
        if ($job->isInvoiced()) {
            throw new BusinessRuleException('This job card already has an invoice.');
        }
        if (! $job->items()->exists()) {
            throw new BusinessRuleException('Add at least one service or part before generating the invoice.');
        }

        return DB::transaction(function () use ($job) {
            $this->recalc($job);
            $job->refresh();

            $invoice = Invoice::create([
                'number'      => Numbering::next('invoice'),
                'type'        => 'garage',
                'job_card_id' => $job->id,
                'customer_id' => $job->customer_id,
                'date'        => now()->toDateString(),
                'subtotal'    => $job->subtotal,
                'discount'    => $job->discount,
                'tax_amount'  => $job->tax_amount,
                'grand_total' => $job->grand_total,
                'balance'     => $job->grand_total,
                'status'      => Invoice::UNPAID,
                'created_by'  => auth()->id(),
            ]);

            foreach ($job->items as $item) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'part_id'     => $item->part_id,
                    'service_id'  => $item->service_id,
                    'description' => $item->description,
                    'qty'         => $item->qty,
                    'rate'        => $item->rate,
                    'tax_rate'    => $job->tax_rate,
                    'amount'      => $item->amount,
                    'cost'        => $item->cost,
                    'stock_cost'  => $item->stock_cost,
                ]);
            }

            // advances paid on the job card now count towards this invoice
            Payment::where('job_card_id', $job->id)->whereNull('invoice_id')->update(['invoice_id' => $invoice->id]);
            $invoice->refreshPaid();

            $this->log($job, $job->status, $job->status, 'Invoice '.$invoice->number.' generated');
            Audit::log('Invoices', 'CREATE', $invoice->number, 'Job '.$job->number.' — '.money($invoice->grand_total));

            return $invoice;
        });
    }

    /** Advance on a job card ($invoice null) or payment on an invoice. */
    public function recordPayment(?JobCard $job, ?Invoice $invoice, float $amount, string $mode, ?string $reference = null, ?string $note = null): Payment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new BusinessRuleException('Enter an amount greater than 0.');
        }
        if ($invoice) {
            if ($invoice->status === Invoice::CANCELLED) {
                throw new BusinessRuleException('This invoice is cancelled.');
            }
            if ($amount > (float) $invoice->balance + 0.01) {
                throw new BusinessRuleException('The amount is more than the balance due ('.money($invoice->balance).').');
            }
            $job ??= $invoice->jobCard;
        }

        return DB::transaction(function () use ($job, $invoice, $amount, $mode, $reference, $note) {
            $payment = Payment::create([
                'number'      => Numbering::next('receipt'),
                'invoice_id'  => $invoice?->id,
                'job_card_id' => $job?->id,
                'customer_id' => $invoice?->customer_id ?? $job?->customer_id,
                'date'        => now()->toDateString(),
                'amount'      => $amount,
                'mode'        => $mode,
                'reference'   => $reference ?: null,
                'note'        => $note,
                'created_by'  => auth()->id(),
            ]);
            $invoice?->refreshPaid();
            Audit::log('Payments', 'CREATE', $payment->number, money($amount).' '.$mode.($invoice ? ' on '.$invoice->number : ' advance on '.$job?->number));

            return $payment;
        });
    }

    /**
     * Undo a payment entered by mistake. Nothing is deleted: a matching minus entry is
     * added (so the day's cash and the audit trail stay honest) and the invoice goes
     * back to unpaid / part paid.
     */
    public function reversePayment(Payment $payment, string $reason): Payment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new BusinessRuleException('Say why the payment is being undone.');
        }
        if ((float) $payment->amount <= 0 || $payment->reverses_id) {
            throw new BusinessRuleException('This entry is itself a refund or an undo, so it cannot be undone.');
        }
        if ($payment->reversal()->exists()) {
            throw new BusinessRuleException("Payment {$payment->number} was already undone.");
        }
        $invoice = $payment->invoice;
        if ($invoice?->status === Invoice::CANCELLED) {
            throw new BusinessRuleException('This sale is cancelled, so its payments cannot be changed.');
        }

        return DB::transaction(function () use ($payment, $invoice, $reason) {
            $undo = Payment::create([
                'number'      => $payment->number.'-U',
                'invoice_id'  => $payment->invoice_id,
                'reverses_id' => $payment->id,
                'job_card_id' => $payment->job_card_id,
                'customer_id' => $payment->customer_id,
                'date'        => now()->toDateString(),
                'amount'      => -1 * (float) $payment->amount,
                'mode'        => $payment->mode,
                'reference'   => $payment->reference,
                'note'        => 'Undo of '.$payment->number.': '.$reason,
                'created_by'  => auth()->id(),
            ]);
            $invoice?->refreshPaid();
            Audit::log('Payments', 'REVERSE', $payment->number, money($payment->amount).' undone'.($invoice ? ' on '.$invoice->number : '').'. Reason: '.$reason);

            return $undo;
        });
    }

    // ------------------------------------------------------------------ helpers

    private function assertEditable(JobCard $job): void
    {
        if ($job->isClosed()) {
            throw new BusinessRuleException('This job card is closed and cannot be changed.');
        }
        if ($job->isInvoiced()) {
            throw new BusinessRuleException('This job card is already invoiced, so its items are locked.');
        }
    }

    private function takeStock(JobCard $job, Part $part, float $qty): void
    {
        $available = (float) Part::whereKey($part->id)->value('stock_qty');
        if ($qty > $available + 0.0001) {
            $have = rtrim(rtrim(number_format($available, 2), '0'), '.');
            throw new BusinessRuleException("Not enough stock for {$part->name}: only {$have} {$part->unit} available.");
        }
        StockMovement::record($part, 'job', out: $qty, unitCost: (float) $part->purchase_price, reference: $job, referenceNo: $job->number);
    }

    private function returnStock(JobCard $job, Part $part, float $qty, string $note): void
    {
        StockMovement::record($part, 'job', in: $qty, unitCost: (float) $part->purchase_price, reference: $job, referenceNo: $job->number, note: $note);
    }

    private function log(JobCard $job, ?string $from, string $to, ?string $note = null): void
    {
        JobStatusHistory::create(['job_card_id' => $job->id, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'user_id' => auth()->id()]);
    }
}
