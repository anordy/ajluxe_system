<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Part;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Support\Audit;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CSV import (e.g. sheets downloaded from the old Google Sheets systems: File → Download → CSV).
 * Each type lists its fields; "aliases" are header names recognised automatically.
 */
class Importer
{
    public const TYPES = [
        'customers' => [
            'label' => 'Customers',
            'fields' => [
                'name'            => ['label' => 'Name', 'required' => true, 'aliases' => ['customer', 'customername', 'fullname', 'client', 'clientname'], 'example' => 'Juma Hassan'],
                'phone'           => ['label' => 'Phone', 'aliases' => ['mobile', 'phoneno', 'phonenumber', 'tel', 'contact', 'simu'], 'example' => '0712345678'],
                'alt_phone'       => ['label' => 'Other phone', 'aliases' => ['altphone', 'phone2', 'alternativephone'], 'example' => ''],
                'email'           => ['label' => 'Email', 'aliases' => ['emailaddress', 'mail'], 'example' => ''],
                'address'         => ['label' => 'Address', 'aliases' => ['location', 'area', 'city'], 'example' => 'Sinza, Dar es Salaam'],
                'tin'             => ['label' => 'TIN', 'aliases' => ['tinno', 'tinnumber'], 'example' => ''],
                'type'            => ['label' => 'Type', 'aliases' => ['customertype', 'category'], 'example' => 'Individual'],
                'credit_limit'    => ['label' => 'Credit limit', 'number' => true, 'aliases' => ['creditlimit'], 'example' => '0'],
                'opening_balance' => ['label' => 'Balance owed', 'number' => true, 'aliases' => ['openingbalance', 'balance', 'outstanding', 'deni', 'amountdue'], 'example' => '0'],
                'notes'           => ['label' => 'Notes', 'aliases' => ['note', 'remarks', 'comments'], 'example' => ''],
            ],
        ],
        'vehicles' => [
            'label' => 'Vehicles',
            'fields' => [
                'reg_no'         => ['label' => 'Reg no', 'required' => true, 'aliases' => ['regno', 'registration', 'registrationno', 'plate', 'plateno', 'numberplate', 'vehicleno', 'carno'], 'example' => 'T 123 ABC'],
                'customer_name'  => ['label' => 'Owner name', 'aliases' => ['customer', 'customername', 'owner', 'ownername', 'client'], 'example' => 'Juma Hassan'],
                'customer_phone' => ['label' => 'Owner phone', 'aliases' => ['phone', 'customerphone', 'ownerphone', 'mobile'], 'example' => '0712345678'],
                'make'           => ['label' => 'Make', 'aliases' => ['brand', 'manufacturer'], 'example' => 'Toyota'],
                'model'          => ['label' => 'Model', 'aliases' => ['vehiclemodel', 'carmodel'], 'example' => 'IST'],
                'year'           => ['label' => 'Year', 'aliases' => ['yom', 'yearofmanufacture', 'modelyear'], 'example' => '2008'],
                'color'          => ['label' => 'Colour', 'aliases' => ['colour'], 'example' => 'Silver'],
                'fuel_type'      => ['label' => 'Fuel', 'aliases' => ['fueltype', 'fuel'], 'example' => 'Petrol'],
                'vin'            => ['label' => 'Chassis / VIN', 'aliases' => ['chassis', 'chassisno', 'vinno'], 'example' => ''],
                'engine_no'      => ['label' => 'Engine no', 'aliases' => ['engine', 'engineno', 'enginenumber'], 'example' => ''],
                'odometer'       => ['label' => 'Odometer (km)', 'number' => true, 'aliases' => ['mileage', 'km', 'odometerkm'], 'example' => '120000'],
            ],
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'fields' => [
                'name'            => ['label' => 'Name', 'required' => true, 'aliases' => ['supplier', 'suppliername', 'company', 'vendor'], 'example' => 'Kariakoo Spares Ltd'],
                'contact_person'  => ['label' => 'Contact person', 'aliases' => ['contact', 'contactname'], 'example' => ''],
                'phone'           => ['label' => 'Phone', 'aliases' => ['mobile', 'phoneno', 'tel'], 'example' => '0713000000'],
                'email'           => ['label' => 'Email', 'aliases' => ['emailaddress'], 'example' => ''],
                'address'         => ['label' => 'Address', 'aliases' => ['location'], 'example' => 'Kariakoo'],
                'tin'             => ['label' => 'TIN', 'aliases' => ['tinno'], 'example' => ''],
                'opening_balance' => ['label' => 'Balance you owe', 'number' => true, 'aliases' => ['openingbalance', 'balance', 'outstanding', 'payable'], 'example' => '0'],
            ],
        ],
        'parts' => [
            'label' => 'Parts (with opening stock)',
            'fields' => [
                'name'           => ['label' => 'Part name', 'required' => true, 'aliases' => ['partname', 'item', 'itemname', 'product', 'productname', 'description', 'sparepart'], 'example' => 'Oil Filter'],
                'sku'            => ['label' => 'SKU / code', 'aliases' => ['partid', 'itemid', 'code', 'itemcode', 'partcode', 'partno', 'partnumber', 'productcode', 'barcode'], 'example' => ''],
                'oem_no'         => ['label' => 'OEM no', 'aliases' => ['oem', 'oemnumber', 'oemno', 'refno'], 'example' => '90915-YZZE1'],
                'brand'          => ['label' => 'Brand', 'aliases' => ['make', 'manufacturer'], 'example' => 'Toyota'],
                'category'       => ['label' => 'Category', 'aliases' => ['group', 'type'], 'example' => 'Filters'],
                'subcategory'    => ['label' => 'Type', 'aliases' => ['subcategory', 'subgroup', 'parttype', 'producttype'], 'example' => 'Brake Pads'],
                'unit'           => ['label' => 'Unit', 'aliases' => ['uom', 'units'], 'example' => 'Pcs'],
                'vehicle_make'   => ['label' => 'Fits make', 'aliases' => ['vehiclemake', 'carmake'], 'example' => 'Toyota'],
                'vehicle_model'  => ['label' => 'Fits model', 'aliases' => ['vehiclemodel', 'carmodel', 'compatible'], 'example' => 'IST, Vitz'],
                'rack_location'  => ['label' => 'Location', 'aliases' => ['rack', 'shelf', 'bin', 'racklocation'], 'example' => 'A3'],
                'purchase_price' => ['label' => 'Buying price', 'number' => true, 'aliases' => ['cost', 'costprice', 'buyingprice', 'purchaseprice', 'bp'], 'example' => '15000'],
                'selling_price'  => ['label' => 'Selling price', 'number' => true, 'aliases' => ['price', 'sellingprice', 'saleprice', 'sp', 'retailprice', 'unitprice'], 'example' => '25000'],
                'min_selling_price' => ['label' => 'Minimum selling price', 'number' => true, 'aliases' => ['minprice', 'minimumprice', 'minsellingprice', 'lowestprice', 'floorprice'], 'example' => '20000'],
                'reorder_level'  => ['label' => 'Re-order level', 'number' => true, 'aliases' => ['reorder', 'reorderlevel', 'minstock', 'minimum'], 'example' => '3'],
                'stock'          => ['label' => 'Stock qty', 'number' => true, 'aliases' => ['qty', 'quantity', 'stockqty', 'instock', 'balance', 'onhand', 'currentstock'], 'example' => '10'],
            ],
        ],
        'services' => [
            'label' => 'Services',
            'fields' => [
                'name'      => ['label' => 'Service name', 'required' => true, 'aliases' => ['service', 'servicename', 'description', 'labour'], 'example' => 'Wheel Alignment'],
                'category'  => ['label' => 'Category', 'aliases' => ['group', 'type'], 'example' => 'Suspension'],
                'pricing'   => ['label' => 'Pricing (fixed / hourly)', 'aliases' => ['pricingtype', 'chargetype', 'basis'], 'example' => 'fixed'],
                'rate'      => ['label' => 'Price / rate', 'number' => true, 'aliases' => ['price', 'amount', 'charge', 'labourcharge', 'cost'], 'example' => '45000'],
                'est_hours' => ['label' => 'Estimated hours', 'number' => true, 'aliases' => ['hours', 'esthours', 'duration'], 'example' => '1'],
            ],
        ],
    ];

    // ------------------------------------------------------------------ reading

    /** Read a CSV file into [headers, rows]. Handles BOM, comma or semicolon. */
    public function read(string $path, int $limit = 20000): array
    {
        $fh = fopen($path, 'r');
        $first = fgets($fh) ?: '';
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $headers = array_map(fn ($h) => trim((string) $h), str_getcsv(rtrim($first, "\r\n"), $delimiter));

        $rows = [];
        while (($r = fgetcsv($fh, 0, $delimiter)) !== false && count($rows) < $limit) {
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }
            $rows[] = array_map(fn ($v) => trim((string) $v), $r);
        }
        fclose($fh);

        return [$headers, $rows];
    }

    /** Best guess: field key => header index (or null). */
    public function guessMapping(string $type, array $headers): array
    {
        $norm = fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
        $h = array_map($norm, $headers);
        $map = [];
        $used = [];
        foreach (self::TYPES[$type]['fields'] as $key => $f) {
            $names = array_merge([$norm($key), $norm($f['label'])], $f['aliases'] ?? []);
            $map[$key] = null;
            foreach ($names as $n) {
                $i = array_search($n, $h, true);
                if ($i !== false && ! in_array($i, $used, true)) {
                    $map[$key] = $i;
                    $used[] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /** Apply a mapping to one CSV row: field key => cleaned value. */
    public function mapRow(string $type, array $row, array $mapping): array
    {
        $out = [];
        foreach (self::TYPES[$type]['fields'] as $key => $f) {
            $i = $mapping[$key] ?? null;
            $v = ($i !== null && $i !== '' && isset($row[(int) $i])) ? trim($row[(int) $i]) : '';
            if (! empty($f['number'])) {
                $v = $v === '' ? null : $this->number($v);
            }
            $out[$key] = $v;
        }

        return $out;
    }

    /** "TZS 364,000" → 364000.0 ; "1.234,50" → 1234.5 */
    public function number(string $v): ?float
    {
        $v = preg_replace('/[^0-9.,\-]/', '', $v);
        if ($v === '' || $v === '-') {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = strrpos($v, ',') > strrpos($v, '.') ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
        } elseif (substr_count($v, ',') === 1 && preg_match('/,\d{1,2}$/', $v)) {
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    // ------------------------------------------------------------------ importing

    /**
     * @return array{created:int, updated:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $type, array $rows, array $mapping, bool $updateExisting = false, bool $createCustomers = true): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $n => $row) {
            $line = $n + 2; // +1 header, +1 human counting
            $d = $this->mapRow($type, $row, $mapping);
            try {
                DB::transaction(function () use ($type, $d, $updateExisting, $createCustomers, &$result) {
                    $status = match ($type) {
                        'customers' => $this->customer($d, $updateExisting),
                        'vehicles'  => $this->vehicle($d, $updateExisting, $createCustomers),
                        'suppliers' => $this->supplier($d, $updateExisting),
                        'parts'     => $this->part($d, $updateExisting),
                        'services'  => $this->service($d, $updateExisting),
                    };
                    $result[$status]++;
                });
            } catch (\InvalidArgumentException $e) {
                $result['errors'][$line] = $e->getMessage();
            } catch (\Throwable $e) {
                report($e);
                $result['errors'][$line] = 'Could not save this row: '.Str::limit($e->getMessage(), 120);
            }
        }

        Audit::log('Import', strtoupper($type), self::TYPES[$type]['label'],
            "created {$result['created']}, updated {$result['updated']}, skipped {$result['skipped']}, errors ".count($result['errors']));

        return $result;
    }

    private function need(array $d, string $key, string $label): string
    {
        if (($d[$key] ?? '') === '' || $d[$key] === null) {
            throw new \InvalidArgumentException("{$label} is empty.");
        }

        return (string) $d[$key];
    }

    /** Keep only non-empty values (so an update never blanks existing data). */
    private function filled(array $values): array
    {
        return array_filter($values, fn ($v) => $v !== null && $v !== '');
    }

    private function phone(?string $p): string
    {
        $p = preg_replace('/[^0-9+]/', '', (string) $p);
        if (str_starts_with($p, '+255')) {
            $p = '0'.substr($p, 4);
        } elseif (str_starts_with($p, '255') && strlen($p) === 12) {
            $p = '0'.substr($p, 3);
        } elseif (strlen($p) === 9 && in_array($p[0], ['6', '7'], true)) {
            $p = '0'.$p; // 712345678 (leading zero dropped by spreadsheets)
        }

        return $p;
    }

    private function customer(array $d, bool $update): string
    {
        $name = $this->need($d, 'name', 'Name');
        $phone = $this->phone($d['phone']);
        $existing = $phone !== '' ? Customer::where('phone', $phone)->first() : Customer::where('name', $name)->whereNull('phone')->first();
        $existing ??= $phone === '' ? Customer::where('name', $name)->first() : null;

        $types = list_options('customer_type');
        $type = collect($types)->first(fn ($t) => strcasecmp($t, (string) $d['type']) === 0) ?? 'Individual';
        $values = $this->filled([
            'name' => $name, 'phone' => $phone ?: null, 'alt_phone' => $this->phone($d['alt_phone']) ?: null, 'email' => $d['email'],
            'address' => $d['address'], 'tin' => $d['tin'], 'type' => $d['type'] !== '' ? $type : null,
            'credit_limit' => $d['credit_limit'], 'opening_balance' => $d['opening_balance'], 'notes' => $d['notes'],
        ]);

        if ($existing) {
            if (! $update) {
                return 'skipped';
            }
            $existing->update($values);

            return 'updated';
        }
        Customer::create($values + ['type' => 'Individual', 'code' => Numbering::code('customer', 'CUS'), 'is_active' => true]);

        return 'created';
    }

    private function vehicle(array $d, bool $update, bool $createCustomers): string
    {
        $reg = Vehicle::normalizeReg($this->need($d, 'reg_no', 'Reg no'));
        $existing = Vehicle::withTrashed()->where('reg_no', $reg)->orWhereRaw("REPLACE(reg_no, ' ', '') = ?", [str_replace(' ', '', $reg)])->first();

        $values = $this->filled([
            'make' => $d['make'], 'model' => $d['model'], 'year' => $d['year'] !== '' && ctype_digit((string) $d['year']) ? (int) $d['year'] : null,
            'color' => $d['color'], 'fuel_type' => $d['fuel_type'], 'vin' => $d['vin'], 'engine_no' => $d['engine_no'],
            'odometer' => $d['odometer'] !== null ? (int) $d['odometer'] : null,
        ]);
        if ($existing) {
            if (! $update) {
                return 'skipped';
            }
            $existing->update($values);

            return 'updated';
        }

        // find the owner by phone, then by name
        $phone = $this->phone($d['customer_phone']);
        $owner = $phone !== '' ? Customer::where('phone', $phone)->first() : null;
        $owner ??= $d['customer_name'] !== '' ? Customer::where('name', $d['customer_name'])->first() : null;
        if (! $owner) {
            if (! $createCustomers || $d['customer_name'] === '') {
                throw new \InvalidArgumentException("Owner not found for {$reg}".($d['customer_name'] ? " ({$d['customer_name']})" : ' — no owner name').'.');
            }
            $owner = Customer::create(['code' => Numbering::code('customer', 'CUS'), 'name' => $d['customer_name'], 'phone' => $phone ?: null, 'type' => 'Individual', 'is_active' => true]);
        }
        Vehicle::create($values + ['customer_id' => $owner->id, 'reg_no' => $reg]);

        return 'created';
    }

    private function supplier(array $d, bool $update): string
    {
        $name = $this->need($d, 'name', 'Name');
        $existing = Supplier::where('name', $name)->first();
        $values = $this->filled([
            'name' => $name, 'contact_person' => $d['contact_person'], 'phone' => $this->phone($d['phone']) ?: null, 'email' => $d['email'],
            'address' => $d['address'], 'tin' => $d['tin'], 'opening_balance' => $d['opening_balance'],
        ]);
        if ($existing) {
            if (! $update) {
                return 'skipped';
            }
            $existing->update($values);

            return 'updated';
        }
        Supplier::create($values + ['code' => Numbering::code('supplier', 'SUP'), 'is_active' => true]);

        return 'created';
    }

    private function part(array $d, bool $update): string
    {
        $name = $this->need($d, 'name', 'Part name');
        $sku = strtoupper(trim((string) $d['sku']));
        $existing = $sku !== '' ? Part::withTrashed()->where('sku', $sku)->first() : null;
        $existing ??= Part::where('name', $name)->where('brand', $d['brand'] ?: null)->first();
        if ($existing === null && $d['brand'] === '') {
            $existing = Part::where('name', $name)->first();
        }

        $values = $this->filled([
            'name' => $name, 'oem_no' => $d['oem_no'], 'brand' => $d['brand'], 'category' => $d['category'], 'subcategory' => $d['subcategory'], 'unit' => $d['unit'],
            'vehicle_make' => $d['vehicle_make'], 'vehicle_model' => $d['vehicle_model'], 'rack_location' => $d['rack_location'],
            'purchase_price' => $d['purchase_price'], 'selling_price' => $d['selling_price'], 'min_selling_price' => $d['min_selling_price'], 'reorder_level' => $d['reorder_level'],
        ]);
        if (isset($values['purchase_price']) && $values['purchase_price'] < 0 || isset($values['selling_price']) && $values['selling_price'] < 0) {
            throw new \InvalidArgumentException('Prices cannot be negative.');
        }

        if ($existing) {
            if (! $update) {
                return 'skipped';
            }
            $existing->update($values); // stock is never changed by an update — use Stock Register → Adjust

            return 'updated';
        }

        $part = Part::create($values + ['sku' => $sku ?: Numbering::code('part', 'PRT', 5), 'unit' => 'Pcs', 'is_active' => true]);
        $qty = (float) ($d['stock'] ?? 0);
        if ($qty > 0) {
            StockMovement::record($part, 'opening', in: $qty, unitCost: (float) $part->purchase_price, note: 'Imported opening stock');
        }

        return 'created';
    }

    private function service(array $d, bool $update): string
    {
        $name = $this->need($d, 'name', 'Service name');
        $existing = Service::where('name', $name)->first();
        $hourly = str_contains(strtolower((string) $d['pricing']), 'hour') || str_contains(strtolower((string) $d['pricing']), 'saa');
        $values = $this->filled([
            'name' => $name, 'category' => $d['category'], 'pricing' => $d['pricing'] !== '' ? ($hourly ? 'hourly' : 'fixed') : null,
            'rate' => $d['rate'], 'est_hours' => $d['est_hours'],
        ]);
        if ($existing) {
            if (! $update) {
                return 'skipped';
            }
            $existing->update($values);

            return 'updated';
        }
        Service::create($values + ['code' => Numbering::code('service', 'SRV', 3), 'pricing' => 'fixed', 'is_active' => true]);

        return 'created';
    }
}
