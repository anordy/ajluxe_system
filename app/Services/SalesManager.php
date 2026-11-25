<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Support\Audit;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;

/**
 * Parts-counter sales (invoices of type "counter").
 * Stock leaves immediately; the sale can be paid in full, in part, or on credit (saved customers only).
 */
class SalesManager
{
    /**
     * @param  array  $lines  [[part_id, qty, rate], ...]
     * @param  array  $sale   customer_id?, walk_in_name?, discount, tax_rate, paid, mode, reference?, notes?
     */
    public function createCounterSale(array $lines, array $sale): Invoice
    {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['qty'] ?? 0) > 0));
        if (! $lines) {
            throw new BusinessRuleException('Add at least one part to the sale.');
        }

        return DB::transaction(function () use ($lines, $sale) {
            // merge duplicate parts so the stock check is per part
            $byPart = [];
            foreach ($lines as $l) {
                $byPart[$l['part_id']] = ($byPart[$l['part_id']] ?? 0) + (float) $l['qty'];
            }
            $parts = Part::whereIn('id', array_keys($byPart))->lockForUpdate()->get()->keyBy('id');
            // parts we buy from a supplier when sold: buy what we don't hold first, so stock and cost stay true
            [$sourced, $pos] = $this->buyFromSuppliers($parts, $byPart, $sale);
            if ($sourced) {
                $parts = Part::whereIn('id', array_keys($byPart))->lockForUpdate()->get()->keyBy('id');
            }
            foreach ($byPart as $partId => $qty) {
                $p = $parts[$partId] ?? throw new BusinessRuleException('A part in the cart no longer exists.');
                if ($qty > (float) $p->stock_qty + 0.0001) {
                    $have = rtrim(rtrim(number_format((float) $p->stock_qty, 2), '0'), '.');
                    throw new BusinessRuleException("Not enough stock for {$p->name}: only {$have} {$p->unit} available.");
                }
            }

            $subtotal = 0;
            foreach ($lines as &$l) {
                $l['qty'] = round((float) $l['qty'], 2);
                $l['rate'] = max(0, round((float) $l['rate'], 2));
                $parts[$l['part_id']]->assertPriceAllowed($l['rate']);
                $l['amount'] = round($l['qty'] * $l['rate'], 2);
                $subtotal += $l['amount'];
            }
            unset($l);

            $discount = round(max(0, (float) ($sale['discount'] ?? 0)), 2);
            if ($discount > $subtotal) {
                throw new BusinessRuleException('The discount cannot be more than the sale total.');
            }
            $taxRate = max(0, min(100, (float) ($sale['tax_rate'] ?? 0)));
            $tax = round(($subtotal - $discount) * $taxRate / 100, 2);
            $total = round($subtotal - $discount + $tax, 2);
            $paid = round(max(0, (float) ($sale['paid'] ?? 0)), 2);

            if ($paid > $total + 0.01) {
                throw new BusinessRuleException('The amount paid is more than the total. Give change and enter the sale total.');
            }
            if ($paid < $total - 0.01 && empty($sale['customer_id'])) {
                throw new BusinessRuleException('Credit (unpaid balance) is only allowed for a saved customer. Pick the customer or take full payment.');
            }

            $invoice = Invoice::create([
                'number'       => Numbering::next('invoice'),
                'type'         => 'counter',
                'customer_id'  => $sale['customer_id'] ?? null,
                'walk_in_name' => empty($sale['customer_id']) ? (trim((string) ($sale['walk_in_name'] ?? '')) ?: null) : null,
                'date'         => now()->toDateString(),
                'subtotal'     => $subtotal,
                'discount'     => $discount,
                'tax_amount'   => $tax,
                'grand_total'  => $total,
                'balance'      => $total,
                'status'       => Invoice::UNPAID,
                'notes'        => trim((string) ($sale['notes'] ?? '')) ?: null,
                'created_by'   => auth()->id(),
            ]);

            foreach ($lines as $l) {
                $part = $parts[$l['part_id']];
                // how much of this line was bought from the supplier (shared out over repeated lines of one part)
                $fromSupplier = min($l['qty'], $sourced[$part->id] ?? 0);
                if ($fromSupplier > 0) {
                    $sourced[$part->id] -= $fromSupplier;
                }
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'part_id'     => $part->id,
                    'description' => $l['description'] ?? $part->name,
                    'qty'         => $l['qty'],
                    'sourced_qty' => $fromSupplier,
                    'source_supplier_id' => $fromSupplier > 0 ? $part->source_supplier_id : null,
                    'rate'        => $l['rate'],
                    'tax_rate'    => $taxRate,
                    'amount'      => $l['amount'],
                    'cost'        => round($l['qty'] * (float) $part->purchase_price, 2),
                    'stock_cost'  => round($l['qty'] * (float) $part->purchase_price, 2),
                ]);
                StockMovement::record($part, 'counter_sale', out: $l['qty'], unitCost: (float) $part->purchase_price,
                    reference: $invoice, referenceNo: $invoice->number);
            }

            foreach ($pos as $po) {
                $po->forceFill(['notes' => trim($po->notes.' Sale '.$invoice->number.'.')])->save();
            }

            if ($paid > 0) {
                Payment::create([
                    'number'      => Numbering::next('receipt'),
                    'invoice_id'  => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'date'        => now()->toDateString(),
                    'amount'      => $paid,
                    'mode'        => $sale['mode'] ?? 'Cash',
                    'reference'   => ($sale['reference'] ?? null) ?: null,
                    'created_by'  => auth()->id(),
                ]);
            }
            $invoice->refreshPaid();

            Audit::log('POS', 'SALE', $invoice->number, money($total).' — '.$invoice->customerName());

            return $invoice;
        });
    }

    /**
     * Void a counter sale: stock goes back, money taken is recorded as refunded, the invoice is marked cancelled.
     */
    public function voidCounterSale(Invoice $invoice, string $reason, string $refundMode = 'Cash'): void
    {
        if (! $invoice->isCounter()) {
            throw new BusinessRuleException('Only counter sales can be voided here.');
        }
        if ($invoice->status === Invoice::CANCELLED) {
            throw new BusinessRuleException('This sale is already voided.');
        }
        if ($invoice->returns()->exists()) {
            throw new BusinessRuleException('This sale has returns recorded. Use a return for the remaining items instead.');
        }

        DB::transaction(function () use ($invoice, $reason, $refundMode) {
            foreach ($invoice->items()->whereNotNull('part_id')->get() as $item) {
                StockMovement::record(Part::withTrashed()->findOrFail($item->part_id), 'counter_sale', in: (float) $item->qty,
                    unitCost: $item->qty > 0 ? (float) $item->cost / (float) $item->qty : 0, reference: $invoice,
                    referenceNo: $invoice->number, note: 'Sale voided');
            }
            $paid = (float) $invoice->payments()->sum('amount');
            if ($paid > 0) {
                Payment::create([
                    'number'      => Numbering::next('receipt'),
                    'invoice_id'  => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'date'        => now()->toDateString(),
                    'amount'      => -$paid,
                    'mode'        => $refundMode,
                    'note'        => 'Refund — sale voided',
                    'created_by'  => auth()->id(),
                ]);
            }
            $invoice->forceFill(['status' => Invoice::CANCELLED, 'paid' => 0, 'balance' => 0, 'void_reason' => $reason])->save();
            Audit::log('POS', 'VOID', $invoice->number, $reason.($paid > 0 ? ' — refunded '.money($paid) : ''));
        });
    }

    /**
     * For parts bought from a supplier when sold, buy the quantity we don't hold: one received purchase order per
     * supplier at the part's cost price (we then owe that shop). Returns [[part_id => qty bought], [POs]].
     */
    private function buyFromSuppliers($parts, array $byPart, array $sale): array
    {
        $need = [];
        foreach ($byPart as $partId => $qty) {
            $p = $parts[$partId] ?? null;
            $short = $p && $p->sourced ? round($qty - max(0, (float) $p->stock_qty), 2) : 0;
            if ($short > 0) {
                if (! $p->source_supplier_id) {
                    throw new BusinessRuleException("{$p->name} is set to be bought from a supplier, but no supplier is picked. Edit the part and pick the supplier.");
                }
                $need[$p->source_supplier_id][$partId] = $short;
            }
        }
        if (! $need) {
            return [[], []];
        }

        $buyer = app(PurchaseManager::class);
        $bought = [];
        $pos = [];
        foreach ($need as $supplierId => $items) {
            $po = $buyer->saveDraft(null, [
                'supplier_id' => $supplierId, 'date' => now()->toDateString(), 'expected_date' => null,
                'notes' => 'Bought from supplier for a sale.'.(! empty($sale['notes']) ? ' '.$sale['notes'].'.' : ''),
            ], collect($items)->map(fn ($qty, $partId) => ['part_id' => $partId, 'qty' => $qty, 'rate' => (float) $parts[$partId]->purchase_price])->values()->all());
            $buyer->markOrdered($po);
            $buyer->receive($po, $po->items()->pluck('qty', 'id')->all(), null, forSale: true);
            $pos[] = $po;
            $bought += $items;
        }

        return [$bought, $pos];
    }
}
