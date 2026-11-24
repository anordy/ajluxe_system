<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\StockReturn;
use App\Models\SupplierPayment;
use App\Support\Audit;
use App\Support\Numbering;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Returns.
 *  Customer return (credit note): stock comes back; the invoice is credited. If the customer had already paid more
 *  than the new total, the difference is refunded (a negative payment).
 *  Supplier return (debit note): stock goes out; the PO is credited. If you had paid more than the new total,
 *  the supplier owes you a refund (a negative supplier payment).
 */
class ReturnManager
{
    /** Parts on an invoice that can still be returned: part_id => [part, rate, sold, returned, returnable]. */
    public function returnableForInvoice(Invoice $invoice): Collection
    {
        $returned = ReturnItem::query()->whereHas('stockReturn', fn ($q) => $q->where('invoice_id', $invoice->id))
            ->selectRaw('part_id, sum(qty) as q')->groupBy('part_id')->pluck('q', 'part_id');

        return $invoice->items()->whereNotNull('part_id')->with('part')->get()->groupBy('part_id')
            ->map(function ($items, $partId) use ($returned) {
                $sold = (float) $items->sum('qty');
                $amount = (float) $items->sum('amount');
                $back = (float) ($returned[$partId] ?? 0);

                return (object) [
                    'part'       => $items->first()->part,
                    'description'=> $items->first()->description,
                    'rate'       => $sold > 0 ? round($amount / $sold, 2) : 0,
                    'sold'       => $sold,
                    'returned'   => $back,
                    'returnable' => max(0, round($sold - $back, 2)),
                ];
            });
    }

    /** Parts received on a PO that can still go back: part_id => [part, rate (landed), received, returned, returnable]. */
    public function returnableForPurchase(PurchaseOrder $po): Collection
    {
        $returned = ReturnItem::query()->whereHas('stockReturn', fn ($q) => $q->where('purchase_order_id', $po->id))
            ->selectRaw('part_id, sum(qty) as q')->groupBy('part_id')->pluck('q', 'part_id');

        return $po->items()->with('part')->where('received_qty', '>', 0)->get()->groupBy('part_id')
            ->map(function ($items, $partId) use ($returned) {
                $received = (float) $items->sum('received_qty');
                $first = $items->first();
                $back = (float) ($returned[$partId] ?? 0);

                return (object) [
                    'part'       => $first->part,
                    'description'=> $first->part->name,
                    'rate'       => round((float) $first->rate * (1 + (float) $first->tax_rate / 100), 2),
                    'received'   => $received,
                    'returned'   => $back,
                    'returnable' => max(0, round($received - $back, 2)),
                    'in_stock'   => (float) $first->part->stock_qty,
                ];
            });
    }

    /** @param array $quantities part_id => qty */
    public function customerReturn(Invoice $invoice, array $quantities, string $reason, string $refundMode = 'Cash'): StockReturn
    {
        if ($invoice->status === Invoice::CANCELLED) {
            throw new BusinessRuleException('This invoice is cancelled.');
        }
        $available = $this->returnableForInvoice($invoice);

        return DB::transaction(function () use ($invoice, $quantities, $reason, $refundMode, $available) {
            $lines = $this->lines($quantities, $available);

            // the credit is the value of the returned goods, including their share of discount and tax
            $sub = (float) $invoice->subtotal;
            $factor = $sub > 0 ? ((float) $invoice->grand_total / $sub) : 1;
            $total = round(collect($lines)->sum(fn ($l) => $l['qty'] * $l['rate']) * $factor, 2);

            $ret = StockReturn::create([
                'number'      => Numbering::next('credit_note'),
                'type'        => 'customer',
                'invoice_id'  => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'date'        => now()->toDateString(),
                'total'       => $total,
                'reason'      => $reason,
                'created_by'  => auth()->id(),
            ]);
            foreach ($lines as $l) {
                $ret->items()->create(['part_id' => $l['part']->id, 'qty' => $l['qty'], 'rate' => $l['rate'], 'amount' => round($l['qty'] * $l['rate'], 2)]);
                StockMovement::record($l['part'], 'return_in', in: $l['qty'], unitCost: (float) $l['part']->purchase_price,
                    reference: $ret, referenceNo: $ret->number, note: 'From '.$invoice->number);
            }

            $invoice->forceFill(['credited' => round((float) $invoice->credited + $total, 2)])->save();
            $invoice->refreshPaid();

            // customer paid more than the new total → give money back
            $over = round(-(float) $invoice->fresh()->balance, 2);
            if ($over > 0.009) {
                Payment::create([
                    'number' => $ret->number, 'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id,
                    'date' => now()->toDateString(), 'amount' => -$over, 'mode' => $refundMode,
                    'note' => 'Refund — '.$ret->number, 'created_by' => auth()->id(),
                ]);
                $invoice->refreshPaid();
                $ret->forceFill(['refund_amount' => $over, 'refund_mode' => $refundMode])->save();
            }
            Audit::log('Returns', 'CUSTOMER', $ret->number, money($total).' on '.$invoice->number.($over > 0 ? ' — refunded '.money($over) : ''));

            return $ret;
        });
    }

    /** @param array $quantities part_id => qty */
    public function supplierReturn(PurchaseOrder $po, array $quantities, string $reason, string $refundMode = 'Cash'): StockReturn
    {
        if (! in_array($po->status, [PurchaseOrder::PARTIAL, PurchaseOrder::RECEIVED], true)) {
            throw new BusinessRuleException('Nothing has been received on this purchase order yet.');
        }
        $available = $this->returnableForPurchase($po);

        return DB::transaction(function () use ($po, $quantities, $reason, $refundMode, $available) {
            $lines = $this->lines($quantities, $available);
            foreach ($lines as $l) {
                $stock = (float) Part::whereKey($l['part']->id)->value('stock_qty');
                if ($l['qty'] > $stock + 0.0001) {
                    throw new BusinessRuleException("Only {$stock} {$l['part']->unit} of {$l['part']->name} in stock — you cannot return more than you have.");
                }
            }
            $total = round(collect($lines)->sum(fn ($l) => $l['qty'] * $l['rate']), 2);

            $ret = StockReturn::create([
                'number'            => Numbering::next('debit_note'),
                'type'              => 'supplier',
                'purchase_order_id' => $po->id,
                'supplier_id'       => $po->supplier_id,
                'date'              => now()->toDateString(),
                'total'             => $total,
                'reason'            => $reason,
                'created_by'        => auth()->id(),
            ]);
            foreach ($lines as $l) {
                $ret->items()->create(['part_id' => $l['part']->id, 'qty' => $l['qty'], 'rate' => $l['rate'], 'amount' => round($l['qty'] * $l['rate'], 2)]);
                StockMovement::record($l['part'], 'return_out', out: $l['qty'], unitCost: $l['rate'],
                    reference: $ret, referenceNo: $ret->number, note: 'To supplier, '.$po->number);
            }

            $po->forceFill(['credited' => round((float) $po->credited + $total, 2)])->save();
            $po->refreshPaid();

            $over = round(-(float) $po->fresh()->balance, 2);
            if ($over > 0.009) {
                SupplierPayment::create([
                    'number' => $ret->number, 'supplier_id' => $po->supplier_id, 'purchase_order_id' => $po->id,
                    'date' => now()->toDateString(), 'amount' => -$over, 'mode' => $refundMode,
                    'note' => 'Refund from supplier — '.$ret->number, 'created_by' => auth()->id(),
                ]);
                $po->refreshPaid();
                $ret->forceFill(['refund_amount' => $over, 'refund_mode' => $refundMode])->save();
            }
            Audit::log('Returns', 'SUPPLIER', $ret->number, money($total).' on '.$po->number);

            return $ret;
        });
    }

    private function lines(array $quantities, Collection $available): array
    {
        $lines = [];
        foreach ($quantities as $partId => $qty) {
            $qty = round((float) $qty, 2);
            if ($qty <= 0) {
                continue;
            }
            $row = $available[$partId] ?? throw new BusinessRuleException('That part is not on this document.');
            if ($qty > $row->returnable + 0.001) {
                $max = rtrim(rtrim(number_format($row->returnable, 2), '0'), '.');
                throw new BusinessRuleException("You can return at most {$max} of {$row->part->name}.");
            }
            $lines[] = ['part' => $row->part, 'qty' => $qty, 'rate' => $row->rate];
        }
        if (! $lines) {
            throw new BusinessRuleException('Enter the quantity being returned for at least one part.');
        }

        return $lines;
    }
}
