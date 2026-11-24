<?php

namespace App\Services;

use App\Models\CarModel;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ListOption;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Audit;
use App\Support\OldSystem\MapsOldParts;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Brings the whole old AJ AUTO PARTS DB workbook across: parts (with opening stock), suppliers, customers,
 * purchase orders, sales (counter invoices), customer and supplier payments, and stock adjustments.
 * Stock is rebuilt from the history in date order, so every part's movements look as they did in the old system
 * and its stock ends at the old "CurrentStock".
 *
 * Safe to run again: everything it created last time is removed and rebuilt.
 */
class OldSystemImport
{
    use MapsOldParts;

    public const STATE_KEY = 'old_system_import';

    /** @var array<string, array> */
    private array $s;

    public array $plan = [];
    public array $skippedPayments = [];
    public array $stockCheck = [];
    public array $summary = [];

    /** stock lines written by this run (so a new run can remove them) */
    private array $movementIds = [];

    public function __construct(array $sheets)
    {
        $this->s = [];
        foreach ($sheets as $name => $rows) {
            $this->s[strtolower(preg_replace('/[^a-z]/i', '', $name))] = $rows;
        }
        foreach (['parts', 'suppliers', 'customers', 'purchases', 'purchaseitems', 'sales', 'salesitems'] as $need) {
            if (! isset($this->s[$need])) {
                throw new RuntimeException("The workbook has no “{$need}” sheet. Use the full AJ AUTO PARTS DB file.");
            }
        }
        $this->prepare();
    }

    private function rows(string $sheet): array
    {
        return $this->s[$sheet] ?? [];
    }

    private static function num($v): float
    {
        return (float) str_replace([',', ' '], '', (string) $v);
    }

    private static function phone($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v)) {
            $v = sprintf('%.0f', (float) $v);
        }
        $p = Phone::normalize($v);

        return $p !== '' ? $p : null;
    }

    private function checkPhone(?string $phone, string $who): void
    {
        if ($phone && ! preg_match('/^0[67]\d{8}$/', $phone)) {
            $this->warnings[] = "{$who}: phone “{$phone}” is not a 10-digit Tanzanian mobile number — imported as written, please correct it.";
        }
    }

    private static function when(?string $date, ?string $time = null): Carbon
    {
        $d = Carbon::parse($date ?: '2000-01-01');
        if ($time && ($t = Carbon::parse($time)) && $t->isSameDay($d)) {
            return $t;
        }

        return $d->copy()->setTime(12, 0);
    }

    // ---------------------------------------------------------------- plan (nothing saved)

    private function prepare(): void
    {
        // parts: same rules as the parts-only import, but stock starts at OpeningStock and the history is replayed
        $plan = [];
        foreach ($this->rows('parts') as $r) {
            if ($p = $this->mapPart($r)) {
                $p['stock'] = max(0, self::num($r['openingstock'] ?? 0));
                $p['current'] = self::num($r['currentstock'] ?? 0);
                $plan[] = $p;
            }
        }
        $this->linkSizes($plan);
        $this->plan = $plan;

        // does each part's history add up to its current stock?
        $po = collect($this->rows('purchases'))->keyBy('pono');
        $sales = collect($this->rows('sales'))->keyBy('invoiceno');
        $in = $out = $adj = [];
        foreach ($this->rows('purchaseitems') as $i) {
            if (strcasecmp((string) ($po[$i['pono']]['status'] ?? ''), 'Received') === 0) {
                $in[$i['partid']] = ($in[$i['partid']] ?? 0) + self::num($i['qty']);
            }
        }
        foreach ($this->rows('salesitems') as $i) {
            if (strcasecmp((string) ($sales[$i['invoiceno']]['status'] ?? ''), 'Cancelled') !== 0) {
                $out[$i['partid']] = ($out[$i['partid']] ?? 0) + self::num($i['qty']);
            }
        }
        foreach ($this->rows('stockadjustments') as $a) {
            $q = self::num($a['qty']);
            $adj[$a['partid']] = ($adj[$a['partid']] ?? 0) + (preg_match('/^(add|in|\+)/i', (string) $a['type']) ? $q : -$q);
        }
        foreach ($plan as $p) {
            $code = $p['part']['sku'];
            $calc = $p['stock'] + ($in[$code] ?? 0) - ($out[$code] ?? 0) + ($adj[$code] ?? 0);
            if (abs($calc - $p['current']) > 0.001) {
                $this->stockCheck[$code] = "{$code} {$p['part']['name']}: opening {$p['stock']} + bought ".($in[$code] ?? 0).' − sold '.($out[$code] ?? 0)
                    .' ± adjusted '.($adj[$code] ?? 0)." = {$calc}, but the old system shows {$p['current']}";
            }
        }

        // payments that look like duplicates: no invoice / PO, and the same party paid / was paid the same amount on a linked one within 7 days
        $this->skippedPayments = array_merge(
            $this->duplicates($this->rows('customerpayments'), 'customerid', 'invoiceno', 'Customer payment'),
            $this->duplicates($this->rows('supplierpayments'), 'supplierid', 'pono', 'Supplier payment'),
        );

        foreach ($this->rows('suppliers') as $r) {
            $this->checkPhone(self::phone($r['phone'] ?? null), "Supplier {$r['supplierid']} {$r['name']}");
        }
        foreach ($this->rows('customers') as $r) {
            $this->checkPhone(self::phone($r['phone'] ?? null), "Customer {$r['customerid']} {$r['name']}");
        }

        $this->summary = [
            'Parts' => count($plan), 'Suppliers' => count($this->rows('suppliers')),
            'Customers' => count(array_filter($this->rows('customers'), fn ($c) => ! $this->isWalkIn($c))),
            'Purchase orders' => count($this->rows('purchases')), 'Sales' => count($this->rows('sales')),
            'Customer payments' => count($this->rows('customerpayments')) - count(array_filter($this->skippedPayments, fn ($x) => str_starts_with($x['kind'], 'Customer'))),
            'Supplier payments' => count($this->rows('supplierpayments')) - count(array_filter($this->skippedPayments, fn ($x) => str_starts_with($x['kind'], 'Supplier'))),
            'Stock adjustments' => count($this->rows('stockadjustments')),
        ];
    }

    private function duplicates(array $payments, string $party, string $doc, string $kind): array
    {
        $skip = [];
        foreach ($payments as $p) {
            if (trim((string) ($p[$doc] ?? '')) !== '') {
                continue;
            }
            $twin = collect($payments)->sortBy(fn ($o) => abs(Carbon::parse($o['date'])->diffInDays(Carbon::parse($p['date']))))->first(fn ($o) => $o !== $p && trim((string) ($o[$doc] ?? '')) !== '' && $o[$party] === $p[$party]
                && abs(self::num($o['amount']) - self::num($p['amount'])) < 0.01
                && abs(Carbon::parse($o['date'])->diffInDays(Carbon::parse($p['date']))) <= 7);
            if ($twin) {
                $skip[$p['paymentno']] = ['kind' => $kind, 'no' => $p['paymentno'], 'why' => money(self::num($p['amount'])).' on '.Carbon::parse($p['date'])->format('d/m/Y')." has no "
                    .($doc === 'pono' ? 'PO' : 'invoice')." and matches {$twin['paymentno']} for {$twin[$doc]}"];
            }
        }

        return $skip;
    }

    private function isWalkIn(array $c): bool
    {
        return stripos((string) ($c['type'] ?? ''), 'walk') !== false || stripos((string) ($c['name'] ?? ''), 'walk-in') !== false;
    }

    // ---------------------------------------------------------------- save

    public function commit(): array
    {
        $by = User::where('role', 'admin')->value('id');
        $codes = array_map(fn ($p) => $p['part']['sku'], $this->plan);

        return DB::transaction(function () use ($by, $codes) {
            $state = json_decode((string) Settings::get(self::STATE_KEY, '{}'), true) ?: [];
            $this->undo($state);

            // parts used in the new system already (sales, job cards, purchases made here) would be counted wrongly: stop
            $partIds = Part::withTrashed()->whereIn('sku', $codes)->pluck('id');
            $foreign = StockMovement::whereIn('part_id', $partIds)->where('type', '!=', 'opening')->get()
                ->map(fn ($m) => $m->reference_no ?: StockMovement::TYPES[$m->type] ?? $m->type)->unique()->filter();
            if ($foreign->isNotEmpty()) {
                throw new RuntimeException('These parts already have sales or purchases recorded in the new system ('.$foreign->take(8)->implode(', ')
                    .'). Remove those test documents first, or import only the parts with "import:old-parts".');
            }

            // ---- parts (details, cars, sizes); stock is rebuilt below
            if (! ListOption::where('list', 'part_category')->where('value', 'Paint & Body')->exists()) {
                ListOption::create(['list' => 'part_category', 'value' => 'Paint & Body', 'sort_order' => (int) ListOption::where('list', 'part_category')->max('sort_order') + 1]);
            }
            $parts = [];
            foreach ($this->plan as $p) {
                $part = Part::withTrashed()->firstOrNew(['sku' => $p['part']['sku']]);
                $data = array_diff_key($p['part'], ['variant_of' => 1]);
                if ($part->exists && (float) $part->rack_location > 0 && (float) $part->rack_location === (float) $p['oldLocation']) {
                    $data['rack_location'] = null;   // an earlier CSV import put the pack size here
                }
                $part->fill($data)->forceFill(['deleted_at' => null])->save();
                $part->fitments()->delete();
                foreach ($p['cars'] as [$make, $model]) {
                    $part->fitments()->firstOrCreate(['car_model_id' => CarModel::firstOrCreate(['make' => $make, 'model' => $model], ['is_active' => true])->id, 'car_engine_id' => null]);
                }
                $parts[$p['part']['sku']] = $part;
            }
            foreach ($this->plan as $p) {
                $parts[$p['part']['sku']]->forceFill(['variant_of' => $p['part']['variant_of'] ? $parts[$p['part']['variant_of']]->id : null])->save();
            }
            StockMovement::whereIn('part_id', collect($parts)->pluck('id'))->delete();
            Part::whereIn('id', collect($parts)->pluck('id'))->update(['stock_qty' => 0]);

            // ---- suppliers and customers (matched by old code, then phone, then name)
            $sup = [];
            foreach ($this->rows('suppliers') as $r) {
                $phone = self::phone($r['phone'] ?? null);
                $s = Supplier::where('code', $r['supplierid'])->first()
                    ?? ($phone ? Supplier::where('phone', $phone)->first() : null)
                    ?? Supplier::where('name', $r['name'])->first() ?? new Supplier;
                $s->fill(['code' => $r['supplierid'], 'name' => $r['name'], 'contact_person' => $r['contactperson'] ?: null, 'phone' => $phone,
                    'email' => $r['email'] ?: null, 'address' => $r['address'] ?: null, 'tin' => ($r['gstin'] ?? '') ?: null,
                    'opening_balance' => self::num($r['openingbalance'] ?? 0), 'is_active' => strcasecmp((string) ($r['status'] ?? 'Active'), 'Inactive') !== 0])->save();
                $sup[$r['supplierid']] = $s->id;
            }
            $cus = [];
            foreach ($this->rows('customers') as $r) {
                if ($this->isWalkIn($r)) {
                    $cus[$r['customerid']] = null;
                    continue;
                }
                $phone = self::phone($r['phone'] ?? null);
                $c = Customer::where('code', $r['customerid'])->first()
                    ?? ($phone ? Customer::where('phone', $phone)->first() : null)
                    ?? Customer::where('name', $r['name'])->first() ?? new Customer;
                $c->fill(['code' => $r['customerid'], 'name' => $r['name'], 'type' => $r['type'] ?: 'Individual', 'phone' => $phone,
                    'email' => $r['email'] ?: null, 'address' => $r['address'] ?: null, 'tin' => ($r['gstin'] ?? '') ?: null,
                    'opening_balance' => self::num($r['openingbalance'] ?? 0),
                    'notes' => trim(implode('. ', array_filter([$r['notes'] ?? '', $r['vehicledetails'] ?? '']))) ?: null,
                    'is_active' => strcasecmp((string) ($r['status'] ?? 'Active'), 'Inactive') !== 0])->save();
                $cus[$r['customerid']] = $c->id;
            }

            // ---- documents, and the stock history in date order
            $events = [];
            $start = collect([...array_column($this->rows('purchases'), 'date'), ...array_column($this->rows('sales'), 'date')])->filter()->min();
            foreach ($this->plan as $p) {
                if ($p['stock'] > 0) {
                    $events[] = [Carbon::parse($start ?: now())->startOfDay(), 0, fn ($at) => $this->move($parts[$p['part']['sku']], 'opening', $p['stock'], 0, $at, null, null, 'Opening stock (old system)')];
                }
            }

            $made = ['invoices' => [], 'purchase_orders' => [], 'payments' => [], 'supplier_payments' => [], 'suppliers' => array_values($sup), 'customers' => array_values(array_filter($cus))];
            $poItems = collect($this->rows('purchaseitems'))->groupBy('pono');
            $poIds = [];
            foreach ($this->rows('purchases') as $r) {
                $status = match (strtolower((string) $r['status'])) { 'received' => 'received', 'cancelled' => 'cancelled', 'partial', 'partially received' => 'partial', default => 'ordered' };
                $po = PurchaseOrder::create([
                    'number' => $r['pono'], 'supplier_id' => $sup[$r['supplierid']] ?? throw new RuntimeException("{$r['pono']}: supplier {$r['supplierid']} not found."),
                    'date' => Carbon::parse($r['date'])->toDateString(), 'expected_date' => ($r['expecteddate'] ?? '') ? Carbon::parse($r['expecteddate'])->toDateString() : null,
                    'status' => $status, 'subtotal' => self::num($r['subtotal']), 'tax_amount' => self::num($r['taxtotal'] ?? 0),
                    'other_charges' => self::num($r['othercharges'] ?? 0), 'grand_total' => self::num($r['grandtotal']), 'credited' => 0, 'paid' => 0,
                    'balance' => self::num($r['grandtotal']), 'notes' => trim(($r['notes'] ?? '').' (old system)'),
                    'received_at' => ($r['receiveddate'] ?? '') ? self::when($r['receiveddate'], $r['updatedat'] ?? null) : null, 'created_by' => $by,
                ]);
                $poIds[$r['pono']] = $po->id;
                $made['purchase_orders'][] = $po->id;
                foreach ($poItems[$r['pono']] ?? [] as $i) {
                    $part = $parts[$i['partid']] ?? throw new RuntimeException("{$r['pono']}: part {$i['partid']} is not in the Parts sheet.");
                    $qty = self::num($i['qty']);
                    PurchaseOrderItem::create(['purchase_order_id' => $po->id, 'part_id' => $part->id, 'qty' => $qty,
                        'received_qty' => $status === 'received' ? $qty : 0, 'rate' => self::num($i['price']), 'tax_rate' => self::num($i['taxpct'] ?? 0),
                        'amount' => self::num($i['linetotal'])]);
                    if ($status === 'received') {
                        $events[] = [self::when($r['receiveddate'] ?: $r['date'], $r['updatedat'] ?? null), 1,
                            fn ($at) => $this->move($part, 'purchase', $qty, 0, $at, $po, $po->number, $r['suppliername'] ?? null, self::num($i['price']))];
                    }
                }
            }

            $saleItems = collect($this->rows('salesitems'))->groupBy('invoiceno');
            $invIds = [];
            foreach ($this->rows('sales') as $r) {
                $cancelled = strcasecmp((string) $r['status'], 'Cancelled') === 0;
                $custId = array_key_exists($r['customerid'], $cus) ? $cus[$r['customerid']] : null;
                $inv = Invoice::create([
                    'number' => $r['invoiceno'], 'type' => 'counter', 'customer_id' => $custId,
                    'walk_in_name' => $custId ? null : ($r['customername'] ?: 'Walk-in'), 'date' => Carbon::parse($r['date'])->toDateString(),
                    'subtotal' => self::num($r['subtotal']), 'discount' => self::num($r['discounttotal'] ?? 0), 'tax_amount' => self::num($r['taxtotal'] ?? 0),
                    'other_charges' => self::num($r['othercharges'] ?? 0) + self::num($r['roundoff'] ?? 0), 'grand_total' => self::num($r['grandtotal']),
                    'credited' => 0, 'paid' => 0, 'balance' => self::num($r['grandtotal']), 'status' => Invoice::UNPAID,
                    'notes' => ($r['notes'] ?? '') ?: null, 'void_reason' => $cancelled ? 'Cancelled in the old system' : null, 'created_by' => $by,
                ]);
                $invIds[$r['invoiceno']] = $inv;
                $made['invoices'][] = $inv->id;
                $at = self::when($r['date'], $r['createdat'] ?? null);
                foreach ($saleItems[$r['invoiceno']] ?? [] as $i) {
                    $part = $parts[$i['partid']] ?? throw new RuntimeException("{$r['invoiceno']}: part {$i['partid']} is not in the Parts sheet.");
                    $qty = self::num($i['qty']);
                    $cost = self::num($i['costprice'] ?? 0);
                    InvoiceItem::create(['invoice_id' => $inv->id, 'part_id' => $part->id, 'description' => $i['partname'] ?: $part->name, 'qty' => $qty,
                        'rate' => self::num($i['rate']), 'tax_rate' => self::num($i['taxpct'] ?? 0), 'amount' => self::num($i['linetotal']),
                        'cost' => round($qty * $cost, 2), 'stock_cost' => round($qty * $cost, 2)]);
                    $events[] = [$at, 2, fn ($when) => $this->move($part, 'counter_sale', 0, $qty, $when, $inv, $inv->number, $inv->customerName(), $cost)];
                    if ($cancelled) {
                        $events[] = [$at->copy()->addMinute(), 3, fn ($when) => $this->move($part, 'counter_sale', $qty, 0, $when, $inv, $inv->number, 'Sale cancelled', $cost)];
                    }
                }
            }

            foreach ($this->rows('stockadjustments') as $a) {
                $part = $parts[$a['partid']] ?? null;
                if (! $part) {
                    continue;
                }
                $q = self::num($a['qty']);
                $add = preg_match('/^(add|in|\+)/i', (string) $a['type']);
                $events[] = [self::when($a['date'], $a['createdat'] ?? null), 4,
                    fn ($when) => $this->move($part, 'adjustment', $add ? $q : 0, $add ? 0 : $q, $when, null, $a['adjno'], trim(($a['reason'] ?? '').': '.($a['notes'] ?? ''), ': '))];
            }

            usort($events, fn ($a, $b) => [$a[0]->timestamp, $a[1]] <=> [$b[0]->timestamp, $b[1]]);
            foreach ($events as [$at, , $run]) {
                $run($at);
            }

            // ---- customer payments (a payment on a cancelled sale was given back: recorded with its refund)
            foreach ($this->rows('customerpayments') as $r) {
                if (isset($this->skippedPayments[$r['paymentno']])) {
                    continue;
                }
                $inv = $invIds[$r['invoiceno'] ?? ''] ?? null;
                $pay = Payment::create(['number' => $r['paymentno'], 'invoice_id' => $inv?->id, 'customer_id' => $inv?->customer_id ?? ($cus[$r['customerid']] ?? null),
                    'date' => Carbon::parse($r['date'])->toDateString(), 'amount' => self::num($r['amount']), 'mode' => $this->mode($r['mode'] ?? ''),
                    'reference' => ($r['reference'] ?? '') ?: $this->modeNote($r['mode'] ?? ''), 'note' => trim(($r['notes'] ?? '').' (old system)'), 'created_by' => $by]);
                $made['payments'][] = $pay->id;
                if ($inv && $inv->void_reason) {
                    $made['payments'][] = Payment::create(['number' => $r['paymentno'].'-R', 'invoice_id' => $inv->id, 'customer_id' => $inv->customer_id,
                        'date' => $pay->date, 'amount' => -$pay->amount, 'mode' => $pay->mode, 'note' => 'Refund — sale cancelled in the old system', 'created_by' => $by])->id;
                }
            }
            $paidCheck = [];
            foreach ($this->rows('sales') as $r) {
                $inv = $invIds[$r['invoiceno']];
                if ($inv->void_reason) {
                    $inv->forceFill(['status' => Invoice::CANCELLED, 'paid' => 0, 'balance' => 0])->save();
                    continue;
                }
                $inv->refreshPaid();
                if (abs((float) $inv->paid - self::num($r['paidamount'])) > 0.01) {
                    $paidCheck[] = "{$r['invoiceno']}: paid ".money($inv->paid).' here, '.money(self::num($r['paidamount'])).' in the old system';
                }
            }

            foreach ($this->rows('supplierpayments') as $r) {
                if (isset($this->skippedPayments[$r['paymentno']])) {
                    continue;
                }
                $made['supplier_payments'][] = SupplierPayment::create(['number' => $r['paymentno'], 'supplier_id' => $sup[$r['supplierid']] ?? null,
                    'purchase_order_id' => $poIds[$r['pono'] ?? ''] ?? null, 'date' => Carbon::parse($r['date'])->toDateString(),
                    'amount' => self::num($r['amount']), 'mode' => $this->mode($r['mode'] ?? ''), 'reference' => ($r['reference'] ?? '') ?: $this->modeNote($r['mode'] ?? ''),
                    'note' => trim(($r['notes'] ?? '').' (old system)'), 'created_by' => $by])->id;
            }
            foreach ($this->rows('purchases') as $r) {
                $po = PurchaseOrder::find($poIds[$r['pono']]);
                $po->refreshPaid();
                if (abs((float) $po->paid - self::num($r['paidamount'])) > 0.01) {
                    $paidCheck[] = "{$r['pono']}: paid ".money($po->paid).' here, '.money(self::num($r['paidamount'])).' in the old system';
                }
            }

            // ---- final check: stock now equals the old CurrentStock
            $stockDiff = [];
            foreach ($this->plan as $p) {
                $now = (float) $parts[$p['part']['sku']]->fresh()->stock_qty;
                if (abs($now - $p['current']) > 0.001) {
                    $stockDiff[] = "{$p['part']['sku']}: {$now} here, {$p['current']} in the old system";
                }
            }

            $made['movements'] = $this->movementIds;
            Settings::set([self::STATE_KEY => json_encode($made)]);
            Audit::log('Import', 'OLD_SYSTEM', 'AJ AUTO PARTS DB', collect($this->summary)->map(fn ($n, $k) => "{$n} {$k}")->implode(', '));

            return ['paid' => $paidCheck, 'stock' => $stockDiff];
        });
    }

    /** Remove the documents the last import created, so it can run again cleanly. */
    private function undo(array $state): void
    {
        if (! $state) {
            return;
        }
        StockMovement::whereIn('id', $state['movements'] ?? [])->delete();
        Payment::whereIn('id', $state['payments'] ?? [])->delete();
        SupplierPayment::whereIn('id', $state['supplier_payments'] ?? [])->delete();
        $inv = $state['invoices'] ?? [];
        $po = $state['purchase_orders'] ?? [];
        StockMovement::where(fn ($q) => $q->where('reference_type', (new Invoice)->getMorphClass())->whereIn('reference_id', $inv))
            ->orWhere(fn ($q) => $q->where('reference_type', (new PurchaseOrder)->getMorphClass())->whereIn('reference_id', $po))->delete();
        InvoiceItem::whereIn('invoice_id', $inv)->delete();
        Invoice::withTrashed()->whereIn('id', $inv)->forceDelete();
        PurchaseOrderItem::whereIn('purchase_order_id', $po)->delete();
        PurchaseOrder::whereIn('id', $po)->delete();
    }

    private function move(Part $part, string $type, float $in, float $out, Carbon $at, $ref, ?string $refNo, ?string $note, float $cost = 0): void
    {
        $m = StockMovement::record($part, $type, in: $in, out: $out, unitCost: $cost ?: (float) $part->purchase_price, reference: $ref, referenceNo: $refNo, note: $note);
        $m->forceFill(['date' => $at, 'created_at' => $at, 'updated_at' => $at])->save();
        $this->movementIds[] = $m->id;
    }

    /** The old mode when it said more than the new name (e.g. "CRDB BANK" → Bank Transfer, reference "CRDB BANK"). */
    private function modeNote(string $m): ?string
    {
        $m = trim($m);

        return $m !== '' && strtoupper($m) !== 'N/A' && strcasecmp($m, $this->mode($m)) !== 0 && ! in_array(strtoupper($m), ['M-PESA', 'MPESA'], true) ? $m : null;
    }

    /** Old payment modes → this system's names. */
    private function mode(string $m): string
    {
        $u = strtoupper(trim($m));

        return match (true) {
            $u === '' || $u === 'N/A'                       => 'Cash',
            str_contains($u, 'LIPA')                        => 'M-Pesa',   // Lipa Namba is M-Pesa
            str_contains($u, 'PESA')                        => 'M-Pesa',
            str_contains($u, 'BANK') || str_contains($u, 'CRDB') || str_contains($u, 'NMB') => 'Bank Transfer',
            default                                         => ucwords(strtolower($m)),
        };
    }
}
