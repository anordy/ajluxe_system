<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use App\Support\Audit;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;

/**
 * Purchase orders: draft → ordered → (part) received.
 * Receiving puts the parts into stock at the PO price and updates each part's buying price.
 */
class PurchaseManager
{
    /**
     * Create or update a draft PO.
     * @param  array  $data   supplier_id, date, expected_date?, tax_rate, other_charges, notes?
     * @param  array  $lines  [[part_id, qty, rate], ...]
     */
    public function saveDraft(?PurchaseOrder $po, array $data, array $lines): PurchaseOrder
    {
        if ($po && ! $po->isDraft()) {
            throw new BusinessRuleException('Only a draft purchase order can be edited.');
        }
        $lines = array_values(array_filter($lines, fn ($l) => ! empty($l['part_id']) && (float) ($l['qty'] ?? 0) > 0));
        if (! $lines) {
            throw new BusinessRuleException('Add at least one part to the purchase order.');
        }

        return DB::transaction(function () use ($po, $data, $lines) {
            $taxRate = max(0, min(100, (float) ($data['tax_rate'] ?? 0)));
            $values = [
                'supplier_id'   => $data['supplier_id'],
                'date'          => $data['date'],
                'expected_date' => $data['expected_date'] ?: null,
                'other_charges' => round(max(0, (float) ($data['other_charges'] ?? 0)), 2),
                'notes'         => trim((string) ($data['notes'] ?? '')) ?: null,
            ];
            if ($po) {
                $po->update($values);
                $po->items()->delete();
            } else {
                $po = PurchaseOrder::create($values + ['number' => Numbering::next('po'), 'status' => PurchaseOrder::DRAFT, 'created_by' => auth()->id()]);
            }

            $subtotal = 0;
            foreach ($lines as $l) {
                $qty = round((float) $l['qty'], 2);
                $rate = round(max(0, (float) $l['rate']), 2);
                $amount = round($qty * $rate, 2);
                $subtotal += $amount;
                $po->items()->create(['part_id' => $l['part_id'], 'qty' => $qty, 'rate' => $rate, 'tax_rate' => $taxRate, 'amount' => $amount]);
            }
            $tax = round($subtotal * $taxRate / 100, 2);
            $total = round($subtotal + $tax + (float) $po->other_charges, 2);
            $po->forceFill(['subtotal' => $subtotal, 'tax_amount' => $tax, 'grand_total' => $total, 'balance' => $total])->save();
            Audit::log('Purchasing', $po->wasRecentlyCreated ? 'CREATE' : 'UPDATE', $po->number, money($total));

            return $po;
        });
    }

    public function markOrdered(PurchaseOrder $po): void
    {
        if (! $po->isDraft()) {
            throw new BusinessRuleException('This purchase order is not a draft.');
        }
        $po->forceFill(['status' => PurchaseOrder::ORDERED])->save();
        Audit::log('Purchasing', 'ORDERED', $po->number);
    }

    /**
     * @param  array  $quantities  [purchase_order_item_id => qty received now]
     */
    public function receive(PurchaseOrder $po, array $quantities, ?string $supplierInvoiceNo = null): void
    {
        if (! $po->canReceive()) {
            throw new BusinessRuleException('Mark the purchase order as ordered before receiving goods.');
        }

        DB::transaction(function () use ($po, $quantities, $supplierInvoiceNo) {
            $any = false;
            foreach ($po->items()->with('part')->get() as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $item->remaining() + 0.001) {
                    throw new BusinessRuleException("You are receiving more {$item->part->name} than was ordered (remaining: {$item->remaining()}).");
                }
                // landed cost per unit includes the PO tax
                $unitCost = round((float) $item->rate * (1 + (float) $item->tax_rate / 100), 2);
                StockMovement::record($item->part, 'purchase', in: $qty, unitCost: $unitCost, reference: $po, referenceNo: $po->number);
                $item->increment('received_qty', $qty);
                Part::whereKey($item->part_id)->update(['purchase_price' => $unitCost]);
                $any = true;
            }
            if (! $any) {
                throw new BusinessRuleException('Enter the quantity received for at least one part.');
            }

            $complete = $po->items()->get()->every(fn ($i) => $i->remaining() <= 0);
            $po->forceFill([
                'status'              => $complete ? PurchaseOrder::RECEIVED : PurchaseOrder::PARTIAL,
                'received_at'         => now(),
                'supplier_invoice_no' => $supplierInvoiceNo ?: $po->supplier_invoice_no,
            ])->save();
            Audit::log('Purchasing', 'RECEIVE', $po->number, $complete ? 'Fully received' : 'Part received');
        });
    }

    public function recordPayment(PurchaseOrder $po, float $amount, string $mode, ?string $reference = null, ?string $note = null): SupplierPayment
    {
        $amount = round($amount, 2);
        if (! in_array($po->status, PurchaseOrder::OWING, true)) {
            throw new BusinessRuleException('Payments can only be recorded on ordered or received purchase orders.');
        }
        if ($amount <= 0) {
            throw new BusinessRuleException('Enter an amount greater than 0.');
        }
        if ($amount > (float) $po->balance + 0.01) {
            throw new BusinessRuleException('The amount is more than the balance owed ('.money($po->balance).').');
        }

        return DB::transaction(function () use ($po, $amount, $mode, $reference, $note) {
            $pay = SupplierPayment::create([
                'number'            => Numbering::next('supplier_payment'),
                'supplier_id'       => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'date'              => now()->toDateString(),
                'amount'            => $amount,
                'mode'              => $mode,
                'reference'         => $reference ?: null,
                'note'              => $note ?: null,
                'created_by'        => auth()->id(),
            ]);
            $po->refreshPaid();
            Audit::log('Purchasing', 'PAYMENT', $pay->number, money($amount).' to '.$po->supplier?->name.' on '.$po->number);

            return $pay;
        });
    }

    public function cancel(PurchaseOrder $po, string $reason): void
    {
        if ($po->status === PurchaseOrder::CANCELLED) {
            return;
        }
        if ($po->items()->where('received_qty', '>', 0)->exists()) {
            throw new BusinessRuleException('Goods were already received on this order. Use a return to supplier instead.');
        }
        if ($po->payments()->exists()) {
            throw new BusinessRuleException('This order has payments recorded and cannot be cancelled.');
        }
        $po->forceFill(['status' => PurchaseOrder::CANCELLED, 'balance' => 0, 'notes' => trim($po->notes."\nCancelled: ".$reason)])->save();
        Audit::log('Purchasing', 'CANCEL', $po->number, $reason);
    }
}
