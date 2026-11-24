<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\JobStatusHistory;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Audit;
use App\Support\Numbering;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Brings the old AJ LUXE AUTO GARAGE DB workbook into this system and merges it with what is already here:
 * customers (merged by phone, then name), mechanics, services, vehicles, job cards with their lines and status history,
 * garage invoices and payments. Job card and invoice numbers are kept (AJ-2026-0001, INV-2026-0001) and numbering
 * continues after them. Garage oil lines are linked to the shop's part without moving stock (the garage bought it from the shop).
 *
 * Safe to run again: what the last run created is removed and rebuilt; merged customers are updated, never deleted.
 */
class OldGarageImport
{
    public const STATE_KEY = 'old_garage_import';

    /** placeholder plates in the old system: these are different cars, never merged */
    private const NO_PLATE = ['T123ABC', 'TXXXXXX', 'TXXX', 'NOPLATE', ''];

    private array $s = [];
    public array $warnings = [];
    public array $merges = [];
    public array $summary = [];
    public array $check = [];

    /** old vehicle id => [plate to use, merged into old vehicle id or null] */
    private array $vehiclePlan = [];

    public function __construct(array $sheets)
    {
        foreach ($sheets as $name => $rows) {
            $this->s[strtolower(preg_replace('/[^a-z]/i', '', $name))] = $rows;
        }
        foreach (['customers', 'vehicles', 'jobcards', 'jobcarditems', 'invoices', 'payments'] as $need) {
            if (! isset($this->s[$need])) {
                throw new RuntimeException("The workbook has no “{$need}” sheet. Use the AJ LUXE AUTO GARAGE DB file.");
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

    private static function plate($v): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $v));
    }

    private static function modelKey($v): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $v));
    }

    private static function at($date, $time = null): ?Carbon
    {
        if (! $date) {
            return null;
        }
        $d = Carbon::parse($date);
        if ($time && ($t = Carbon::parse($time)) && $t->isSameDay($d)) {
            return $t;
        }

        return $d->copy()->setTime(9, 0);
    }

    // ---------------------------------------------------------------- plan

    private function prepare(): void
    {
        // money adds up in the old system?
        $items = [];
        foreach ($this->rows('jobcarditems') as $i) {
            $items[$i['jobcardno']] = ($items[$i['jobcardno']] ?? 0) + self::num($i['amount']);
        }
        foreach ($this->rows('jobcards') as $j) {
            if (abs(($items[$j['jobcardno']] ?? 0) - self::num($j['subtotal'])) > 1) {
                $this->check[] = "{$j['jobcardno']}: lines add up to ".money($items[$j['jobcardno']] ?? 0).', subtotal says '.money(self::num($j['subtotal']));
            }
        }
        $paid = [];
        foreach ($this->rows('payments') as $p) {
            $paid[$p['invoiceno']] = ($paid[$p['invoiceno']] ?? 0) + self::num($p['amount']);
        }
        foreach ($this->rows('invoices') as $i) {
            if (abs(($paid[$i['invoiceno']] ?? 0) - self::num($i['amountreceived'])) > 1) {
                $this->check[] = "{$i['invoiceno']}: payments add up to ".money($paid[$i['invoiceno']] ?? 0).', invoice says '.money(self::num($i['amountreceived']));
            }
        }

        // customers: who will be merged with a customer already here
        foreach ($this->rows('customers') as $c) {
            if ($hit = $this->findCustomer($c)) {
                $this->merges[] = "{$c['customerid']} {$c['customername']} → existing {$hit->code} {$hit->name}".($hit->phone !== self::phone($c['mobile']) ? ' (by name; phone '.self::phone($c['mobile']).' kept as other phone)' : '');
            }
        }

        // vehicles: the same plate twice is one car when the model matches; placeholder plates stay separate cars
        $byPlate = [];
        foreach ($this->rows('vehicles') as $v) {
            $byPlate[self::plate($v['registrationno'])][] = $v;
        }
        foreach ($byPlate as $plate => $list) {
            if (in_array($plate, self::NO_PLATE, true)) {
                foreach ($list as $v) {
                    $this->vehiclePlan[$v['vehicleid']] = ['plate' => 'NO PLATE '.$v['vehicleid'], 'into' => null];
                }
                if ($list) {
                    $this->warnings[] = count($list)." vehicles had the made-up plate “{$list[0]['registrationno']}”: imported as “NO PLATE VEH-…” — add the real plates when you see the cars.";
                }
                continue;
            }
            $first = array_shift($list);
            $this->vehiclePlan[$first['vehicleid']] = ['plate' => strtoupper(trim(preg_replace('/\s+/', ' ', (string) $first['registrationno']))), 'into' => null];
            foreach ($list as $v) {
                $a = self::modelKey($first['model'] ?: $first['make']);
                $b = self::modelKey($v['model'] ?: $v['make']);
                similar_text($a, $b, $pct);
                if ($a === '' || $b === '' || $pct >= 60 || str_contains($a, $b) || str_contains($b, $a)) {
                    $this->vehiclePlan[$v['vehicleid']] = ['plate' => null, 'into' => $first['vehicleid']];
                } else {
                    $this->vehiclePlan[$v['vehicleid']] = ['plate' => $this->vehiclePlan[$first['vehicleid']]['plate'].' ('.$v['vehicleid'].')', 'into' => null];
                    $this->warnings[] = "Plate {$first['registrationno']} is used for a {$first['model']} ({$first['vehicleid']}) and a {$v['model']} ({$v['vehicleid']}): kept as two cars — please check the plate.";
                }
            }
        }

        $parts = $this->rows('parts');
        foreach ($this->rows('jobcarditems') as $i) {
            if (strcasecmp((string) $i['linetype'], 'Part') === 0 && ! $this->shopPart($i)) {
                $this->warnings[] = "{$i['jobcardno']}: part “{$i['description']}” was not found in the shop's parts — imported as a line without stock.";
            }
        }

        $this->summary = [
            'Customers' => count($this->rows('customers')).' ('.count($this->merges).' merged with existing)',
            'Mechanics' => count($this->rows('mechanics')),
            'Services' => count($this->rows('services')),
            'Vehicles' => count(array_filter($this->vehiclePlan, fn ($v) => $v['into'] === null)).' (from '.count($this->rows('vehicles')).' rows, duplicates merged)',
            'Job cards' => count($this->rows('jobcards')),
            'Job card lines' => count($this->rows('jobcarditems')),
            'Status changes' => count($this->rows('jobstatushistory')),
            'Invoices' => count($this->rows('invoices')),
            'Payments' => count($this->rows('payments')),
            'Owed to you' => money(array_sum(array_map(fn ($i) => self::num($i['balance']), $this->rows('invoices')))),
        ];
    }

    private function findCustomer(array $c): ?Customer
    {
        $phone = self::phone($c['mobile'] ?? null);

        return ($phone ? Customer::where('phone', $phone)->orWhere('alt_phone', $phone)->first() : null)
            ?? Customer::whereRaw('lower(trim(name)) = ?', [mb_strtolower(trim((string) $c['customername']))])->first();
    }

    /** The shop's part for a garage part line (the garage oil → the shop's Atlantic 5W-30 5L). */
    private function shopPart(array $line): ?Part
    {
        $name = (string) $line['description'];
        $old = collect($this->rows('parts'))->firstWhere('partid', $line['refid']);
        $words = trim(($old['brand'] ?? '').' '.$name);
        if (preg_match('/(\d{1,2}W-?\d{2})/i', $words, $m)) {
            return Part::where('specs->Viscosity', strtoupper($m[1]))->where(fn ($q) => $q->where('brand', 'like', '%'.($old['brand'] ?? 'Atlantic').'%'))
                ->orderByRaw('variant_of is not null')->first();
        }

        return Part::where('name', $name)->first();
    }

    // ---------------------------------------------------------------- save

    public function commit(): array
    {
        $by = User::where('role', 'admin')->value('id');

        return DB::transaction(function () use ($by) {
            $this->undo(json_decode((string) Settings::get(self::STATE_KEY, '{}'), true) ?: []);
            $made = ['customers' => [], 'mechanics' => [], 'services' => [], 'vehicles' => [], 'job_cards' => [], 'invoices' => [], 'payments' => []];

            // ---- customers (merge or add)
            $cus = [];
            foreach ($this->rows('customers') as $c) {
                $phone = self::phone($c['mobile'] ?? null);
                $hit = $this->findCustomer($c);
                if ($hit) {
                    $fill = [];
                    if ($phone && $hit->phone !== $phone && ! $hit->alt_phone) {
                        $fill['alt_phone'] = $phone;
                    }
                    foreach (['email' => 'email', 'address' => 'address'] as $k => $col) {
                        if (! $hit->{$col} && ($c[$k] ?? '')) {
                            $fill[$col] = $c[$k];
                        }
                    }
                    $note = 'Garage system '.$c['customerid'].($c['notes'] ? ': '.$c['notes'] : '');
                    if (! str_contains((string) $hit->notes, 'Garage system '.$c['customerid'])) {
                        $fill['notes'] = trim(($hit->notes ? $hit->notes.'. ' : '').$note);
                    }
                    $hit->fill($fill)->save();
                    $cus[$c['customerid']] = $hit->id;
                    continue;
                }
                $name = trim(preg_replace('/\s+/', ' ', (string) $c['customername']));
                $new = Customer::create([
                    'code' => $this->freeCode(Customer::class, 'customer', 'CUS', 4), 'name' => $name,
                    'type' => preg_match('/insurance|ltd|limited|company|bank/i', $name) ? 'Company' : 'Individual',
                    'phone' => $phone, 'email' => $c['email'] ?: null, 'address' => $c['address'] ? trim(preg_replace('/\s+/', ' ', $c['address'])) : null,
                    'tin' => ($c['gstno'] ?? '') ?: null, 'notes' => trim('Garage system '.$c['customerid'].($c['notes'] ? ': '.$c['notes'] : '')),
                    'is_active' => strcasecmp((string) ($c['status'] ?? 'Active'), 'Inactive') !== 0,
                ]);
                $cus[$c['customerid']] = $new->id;
                $made['customers'][] = $new->id;
            }

            // ---- mechanics (by phone, then name)
            $mech = [];
            foreach ($this->rows('mechanics') as $m) {
                $phone = self::phone($m['mobile'] ?? null);
                $name = trim((string) $m['name']);
                $hit = ($phone ? Mechanic::where('phone', $phone)->first() : null) ?? Mechanic::whereRaw('lower(trim(name)) = ?', [mb_strtolower($name)])->first();
                if (! $hit) {
                    $hit = Mechanic::create(['name' => $name, 'phone' => $phone, 'specialization' => $m['specialization'] ?: null,
                        'is_active' => strcasecmp((string) ($m['status'] ?? 'Active'), 'Inactive') !== 0]);
                    $made['mechanics'][] = $hit->id;
                }
                $mech[$m['mechanicid']] = $hit->id;
            }

            // ---- services (by name; new ones keep their old price)
            $svc = [];
            foreach ($this->rows('services') as $s) {
                $name = trim(preg_replace('/\s+/', ' ', (string) $s['servicename']));
                $hit = Service::whereRaw('lower(trim(name)) = ?', [mb_strtolower($name)])->first();
                if (! $hit) {
                    $hit = Service::create(['code' => $this->freeCode(Service::class, 'service', 'SRV', 3), 'name' => $name,
                        'category' => $s['category'] ?: 'Other', 'pricing' => 'fixed', 'rate' => self::num($s['laborcharge'] ?? 0),
                        'est_hours' => self::num($s['esttimehrs'] ?? 0), 'is_active' => strcasecmp((string) ($s['status'] ?? 'Active'), 'Inactive') !== 0]);
                    $made['services'][] = $hit->id;
                }
                $svc[$s['serviceid']] = $hit->id;
            }

            // ---- vehicles
            $veh = [];
            foreach ($this->rows('vehicles') as $v) {
                $plan = $this->vehiclePlan[$v['vehicleid']];
                if ($plan['into']) {
                    continue;
                }
                $existing = Vehicle::withTrashed()->whereRaw("upper(replace(reg_no, ' ', '')) = ?", [self::plate($plan['plate'])])->first();
                if ($existing) {
                    $veh[$v['vehicleid']] = $existing->id;
                    continue;
                }
                $vehicle = Vehicle::create([
                    'customer_id' => $cus[$v['customerid']] ?? throw new RuntimeException("{$v['vehicleid']}: customer {$v['customerid']} not found."),
                    'reg_no' => $plan['plate'], 'make' => $v['make'] ?: null, 'model' => $v['model'] ?: null,
                    'year' => self::num($v['year'] ?? 0) ?: null, 'fuel_type' => $v['fueltype'] ?: null, 'color' => $v['color'] ?: null,
                    'vin' => ($v['chassisno'] ?? '') ?: null, 'engine_no' => ($v['engineno'] ?? '') ?: null,
                    'odometer' => self::num($v['odometer'] ?? 0) ?: null,
                    'notes' => trim(implode('. ', array_filter([$v['notes'] ?? '', ($v['insuranceexpiry'] ?? '') ? 'Insurance expires '.Carbon::parse($v['insuranceexpiry'])->format('d M Y') : '', 'Garage system '.$v['vehicleid']]))),
                ]);
                $veh[$v['vehicleid']] = $vehicle->id;
                $made['vehicles'][] = $vehicle->id;
            }
            foreach ($this->vehiclePlan as $id => $plan) {
                if ($plan['into']) {
                    $veh[$id] = $veh[$plan['into']];
                }
            }

            // ---- job cards, lines and status history
            $hist = collect($this->rows('jobstatushistory'))->groupBy('jobcardno');
            $lines = collect($this->rows('jobcarditems'))->groupBy('jobcardno');
            $jobs = [];
            foreach ($this->rows('jobcards') as $j) {
                $mechIds = array_values(array_filter(array_map('trim', explode(',', (string) $j['mechanicids']))));
                $others = array_map(fn ($id) => Mechanic::find($mech[$id] ?? 0)?->name, array_slice($mechIds, 1));
                $sub = self::num($j['subtotal']);
                $disc = self::num($j['discount']);
                $deliveredAt = collect($hist[$j['jobcardno']] ?? [])->where('tostatus', 'Delivered')->max('timestamp');
                $job = JobCard::create([
                    'number' => $j['jobcardno'], 'customer_id' => $cus[$j['customerid']] ?? throw new RuntimeException("{$j['jobcardno']}: customer {$j['customerid']} not found."),
                    'vehicle_id' => $veh[$j['vehicleid']] ?? throw new RuntimeException("{$j['jobcardno']}: vehicle {$j['vehicleid']} not found."),
                    'mechanic_id' => $mechIds ? ($mech[$mechIds[0]] ?? null) : null,
                    'status' => in_array($j['status'], [...JobCard::STATUSES, JobCard::CANCELLED], true) ? $j['status'] : JobCard::OPEN,
                    'priority' => $j['priority'] ?: 'Normal', 'odometer_in' => self::num($j['odometerin'] ?? 0) ?: null, 'fuel_level' => $j['fuellevel'] ?: null,
                    'complaints' => $j['complaints'] ?: null,
                    'inspection_notes' => trim(implode("\n", array_filter([$j['inspectionnotes'] ?? '', ($j['conditionnotes'] ?? '') ? 'Condition: '.$j['conditionnotes'] : '']))) ?: null,
                    'work_notes' => trim(implode("\n", array_filter([$j['worknotes'] ?? '', array_filter($others) ? 'Also worked on it: '.implode(', ', array_filter($others)) : '']))) ?: null,
                    'received_at' => self::at($j['date'], $j['createdon'] ?? null), 'promised_at' => self::at($j['estdelivery'] ?? null),
                    'delivered_at' => $j['status'] === 'Delivered' ? ($deliveredAt ? Carbon::parse($deliveredAt) : self::at($j['updatedon'] ?? $j['date'])) : null,
                    'subtotal' => $sub, 'discount_rate' => $sub > 0 ? round($disc / $sub * 100, 2) : 0, 'discount' => $disc,
                    'tax_rate' => self::num($j['tax'] ?? 0), 'tax_amount' => self::num($j['taxamount'] ?? 0), 'grand_total' => self::num($j['grandtotal']),
                    'created_by' => $by,
                ]);
                $job->forceFill(['created_at' => self::at($j['createdon'] ?? $j['date']), 'updated_at' => self::at($j['updatedon'] ?? $j['date'])])->save();
                $jobs[$j['jobcardno']] = $job;
                $made['job_cards'][] = $job->id;

                foreach ($lines[$j['jobcardno']] ?? [] as $i) {
                    $isPart = strcasecmp((string) $i['linetype'], 'Part') === 0;
                    $part = $isPart ? $this->shopPart($i) : null;
                    $qty = self::num($i['qty']) ?: 1;
                    JobCardItem::create([
                        'job_card_id' => $job->id, 'type' => $isPart && $part ? 'part' : 'service',
                        'service_id' => $isPart ? null : ($svc[$i['refid']] ?? null), 'part_id' => $part?->id,
                        'description' => trim((string) $i['description']) ?: 'Work', 'qty' => $qty, 'rate' => self::num($i['rate']),
                        'amount' => self::num($i['amount']), 'cost' => self::num($i['cost'] ?? 0),
                        // the shop already sold this stock in the parts system: no second shop margin
                        'stock_cost' => self::num($i['cost'] ?? 0),
                    ]);
                }
                foreach ($hist[$j['jobcardno']] ?? [] as $h) {
                    $row = JobStatusHistory::create(['job_card_id' => $job->id, 'from_status' => $h['fromstatus'] ?: null, 'to_status' => $h['tostatus'],
                        'note' => trim(($h['notes'] ?? '').(($h['changedby'] ?? '') ? ' — '.$h['changedby'] : ''), ' —') ?: null, 'user_id' => null]);
                    $row->timestamps = false;
                    $row->forceFill(['created_at' => Carbon::parse($h['timestamp'])])->save();
                }
            }

            // ---- invoices and payments
            $invs = [];
            foreach ($this->rows('invoices') as $r) {
                $job = $jobs[$r['jobcardno']] ?? null;
                $inv = Invoice::create([
                    'number' => $r['invoiceno'], 'type' => 'garage', 'job_card_id' => $job?->id,
                    'customer_id' => $cus[$r['customerid']] ?? $job?->customer_id, 'date' => Carbon::parse($r['invoicedate'])->toDateString(),
                    'subtotal' => self::num($r['subtotal']), 'discount' => self::num($r['discount']), 'tax_amount' => self::num($r['taxamount'] ?? 0),
                    'other_charges' => 0, 'grand_total' => self::num($r['grandtotal']), 'credited' => 0, 'paid' => 0,
                    'balance' => self::num($r['grandtotal']), 'status' => Invoice::UNPAID, 'created_by' => $by,
                ]);
                $invs[$r['invoiceno']] = $inv;
                $made['invoices'][] = $inv->id;
                foreach ($job?->items ?? [] as $item) {
                    InvoiceItem::create(['invoice_id' => $inv->id, 'part_id' => $item->part_id, 'service_id' => $item->service_id, 'description' => $item->description,
                        'qty' => $item->qty, 'rate' => $item->rate, 'tax_rate' => 0, 'amount' => $item->amount, 'cost' => $item->cost, 'stock_cost' => $item->stock_cost]);
                }
            }
            foreach ($this->rows('payments') as $p) {
                $inv = $invs[$p['invoiceno']] ?? null;
                $made['payments'][] = Payment::create([
                    'number' => $p['paymentid'], 'invoice_id' => $inv?->id, 'job_card_id' => $inv?->job_card_id ?? ($jobs[$p['jobcardno']] ?? null)?->id,
                    'customer_id' => $inv?->customer_id ?? ($cus[$p['customerid']] ?? null), 'date' => Carbon::parse($p['date'])->toDateString(),
                    'amount' => self::num($p['amount']), 'mode' => preg_match('/pesa/i', (string) $p['mode']) ? 'M-Pesa' : ($p['mode'] ?: 'Cash'),
                    'reference' => ($p['referenceno'] ?? '') ?: null, 'note' => trim(($p['notes'] ?? '').' (old garage system)'), 'created_by' => $by,
                ])->id;
            }
            $diff = [];
            foreach ($this->rows('invoices') as $r) {
                $inv = $invs[$r['invoiceno']];
                $inv->refreshPaid();
                if (abs((float) $inv->balance - self::num($r['balance'])) > 0.01) {
                    $diff[] = "{$r['invoiceno']}: balance ".money($inv->balance).' here, '.money(self::num($r['balance'])).' in the old system';
                }
            }

            // ---- numbering continues after the old numbers (AJ-2026-0101, INV-2026-0076)
            $this->continueNumbering('job_card', 'AJ', JobCard::pluck('number'));
            $this->continueNumbering('invoice', 'INV', Invoice::withTrashed()->pluck('number'));

            Settings::set([self::STATE_KEY => json_encode($made)]);
            Audit::log('Import', 'OLD_GARAGE', 'AJ LUXE AUTO GARAGE DB', collect($this->summary)->map(fn ($n, $k) => "{$k}: {$n}")->implode(', '));

            return $diff;
        });
    }

    private function undo(array $state): void
    {
        if (! $state) {
            return;
        }
        $jobs = $state['job_cards'] ?? [];
        Payment::whereIn('id', $state['payments'] ?? [])->delete();
        InvoiceItem::whereIn('invoice_id', $state['invoices'] ?? [])->delete();
        Invoice::withTrashed()->whereIn('id', $state['invoices'] ?? [])->forceDelete();
        JobStatusHistory::whereIn('job_card_id', $jobs)->delete();
        JobCardItem::whereIn('job_card_id', $jobs)->delete();
        DB::table('job_cards')->whereIn('id', $jobs)->delete();
        Vehicle::withTrashed()->whereIn('id', $state['vehicles'] ?? [])->forceDelete();
        Service::whereIn('id', $state['services'] ?? [])->whereNotIn('id', DB::table('job_card_items')->whereNotNull('service_id')->select('service_id'))->delete();
        Mechanic::whereIn('id', $state['mechanics'] ?? [])->delete();
        Customer::withTrashed()->whereIn('id', $state['customers'] ?? [])->whereDoesntHave('invoices')->forceDelete();
    }

    /** next free running code (customers / services may already use some numbers) */
    private function freeCode(string $model, string $key, string $prefix, int $pad): string
    {
        do {
            $code = Numbering::code($key, $prefix, $pad);
        } while ((method_exists($model, 'bootSoftDeletes') ? $model::withTrashed() : $model::query())->where('code', $code)->exists());

        return $code;
    }

    /** Make the year's counter start after the highest old number, e.g. AJ-2026-0100 → next AJ-2026-0101. */
    private function continueNumbering(string $key, string $prefix, $numbers): void
    {
        $max = [];
        foreach ($numbers as $n) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d{4})-(\d+)$/', (string) $n, $m)) {
                $max[(int) $m[1]] = max($max[(int) $m[1]] ?? 0, (int) $m[2]);
            }
        }
        if (! $max) {
            return;
        }
        Settings::set(['prefix_'.$key => $prefix]);
        foreach ($max as $year => $last) {
            DB::table('sequences')->insertOrIgnore(['key' => $key, 'year' => $year, 'last_number' => 0]);
            DB::table('sequences')->where(['key' => $key, 'year' => $year])->where('last_number', '<', $last)->update(['last_number' => $last]);
        }
    }
}
