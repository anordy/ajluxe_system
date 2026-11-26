<?php

namespace Database\Seeders;

use App\Models\CarModel;
use App\Models\Part;
use App\Models\PartVehicle;
use App\Models\Supplier;
use App\Support\Numbering;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Spark plugs from Kitonga Spareparts' price list (rows 160–171).
 *
 * They are added as "buy from supplier when sold" parts: on the website as normal stock,
 * no stock and no stock value here, bought from Kitonga only when they sell.
 * Safe to run again: existing rows (same part number from Kitonga) are updated, not doubled.
 *
 *   php artisan db:seed --class=KitongaSparkPlugSeeder --force
 */
class KitongaSparkPlugSeeder extends Seeder
{
    public const SUPPLIER = 'Kitonga Spareparts';

    /**
     * [part number, brand, name, fits as written on the list, [[make, model], …] for the car finder, buying, selling, pack]
     * Brand: Toyota 90919-… numbers and the IK/VK/IXU iridium codes are Denso; Nissan 22401-5M015 is NGK.
     */
    private const ROWS = [
        ['22401-5M015', 'NGK', 'Spark Plug 22401-5M015 (Nissan)', 'Nissan, Infiniti, X-Trail, Murano',
            [['Nissan', 'X-Trail'], ['Nissan', 'Murano']], 30000, 35000, null],
        ['90919-01249', 'Denso', 'Spark Plug 90919-01249', 'Toyota Crown, Mark X',
            [['Toyota', 'Crown'], ['Toyota', 'Mark X']], 25000, 30000, null],
        ['22401-01247', 'Denso', 'Spark Plug 22401-01247', 'Lexus, Toyota RAV4, Camry',
            [['Toyota', 'RAV4'], ['Toyota', 'Camry']], 25000, 30000, null],
        ['90919-01191', 'Denso', 'Spark Plug 90919-01191', 'Toyota Crown, Hilux Surf, FJ Cruiser, 4Runner',
            [['Toyota', 'Crown']], 20000, 25000, null],
        ['90919-01198', 'Denso', 'Spark Plug 90919-01198', 'Toyota Corolla, Carina, Camry',
            [['Toyota', 'Corolla'], ['Toyota', 'Camry']], 20000, 25000, null],
        ['90919-01210', 'Denso', 'Spark Plug 90919-01210', 'Toyota IST, Vitz, Raum, RAV4, Land Cruiser, Alphard and more',
            [['Toyota', 'IST'], ['Toyota', 'Vitz'], ['Toyota', 'Raum'], ['Toyota', 'RAV4'], ['Toyota', 'Land Cruiser'], ['Toyota', 'Alphard']], 18000, 25000, null],
        ['90919-01221', 'Denso', 'Spark Plug 90919-01221', 'Toyota RAV4, Vista, Noah, Wish, Nadia, Gaia, Opa, Sienta',
            [['Toyota', 'RAV4'], ['Toyota', 'Noah'], ['Toyota', 'Wish'], ['Toyota', 'Opa']], 25000, 30000, null],
        ['90919-01253', 'Denso', 'Spark Plug 90919-01253', 'Toyota Auris, hybrids, Nissan Dualis',
            [['Nissan', 'Dualis']], 25000, 30000, null],
        ['IK20 5304', 'Denso', 'Iridium Power Spark Plug IK20 (4 pack)', 'Toyota IST, Vitz, Raum, RAV4, Land Cruiser, Alphard and more',
            [['Toyota', 'IST'], ['Toyota', 'Vitz'], ['Toyota', 'Raum'], ['Toyota', 'RAV4'], ['Toyota', 'Land Cruiser'], ['Toyota', 'Alphard']], 12000, 15000, 4],
        ['VK20 56044', 'Denso', 'Iridium Spark Plug VK20 (10 pack)', 'Mitsubishi Pajero, Toyota Rumion, Sienta',
            [['Mitsubishi', 'Pajero'], ['Toyota', 'Rumion']], 30000, 35000, 10],
        ['IK20TT 4702', 'Denso', 'Iridium TT Spark Plug IK20TT (4 pack)', 'Mitsubishi Pajero, Toyota Rumion, Sienta',
            [['Mitsubishi', 'Pajero'], ['Toyota', 'Rumion']], 12000, 15000, 4],
        ['IXU22HPR 3474', 'Denso', 'Iridium Power Spark Plug IXU22HPR (4 pack)', 'Nissan Dualis',
            [['Nissan', 'Dualis']], 14000, 16000, 4],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $supplier = Supplier::firstOrCreate(['name' => self::SUPPLIER], ['code' => Numbering::code('supplier', 'SUP'), 'is_active' => true]);
            $added = $updated = 0;

            foreach (self::ROWS as [$no, $brand, $name, $fits, $cars, $buy, $sell, $pack]) {
                $makes = collect($cars)->pluck(0)->unique();
                $values = [
                    'name' => $name, 'brand' => $brand, 'category' => 'Car Parts', 'subcategory' => 'Spark Plugs',
                    'unit' => 'Pcs', 'purchase_price' => $buy, 'selling_price' => $sell,
                    'vehicle_make' => $makes->count() === 1 ? $makes->first() : null,
                    'vehicle_model' => $fits,
                    'specs' => array_filter(['Part number' => $no, 'Brand' => $brand, 'Fits' => $fits, 'Pack' => $pack ? $pack.' plugs' : null]),
                    'is_active' => true, 'show_online' => true,
                    'sourced' => true, 'source_supplier_id' => $supplier->id,   // bought from Kitonga when sold: no stock here
                ];

                $part = Part::where('oem_no', $no)->where('source_supplier_id', $supplier->id)->first();
                if ($part) {
                    $part->update($values);
                    $updated++;
                } else {
                    $part = Part::create($values + ['oem_no' => $no, 'sku' => $this->freeSku()]);
                    $added++;
                }

                // link to the website's car finder where the car is on the car list
                $ids = collect($cars)->map(fn ($c) => CarModel::where('make', $c[0])->where('model', $c[1])->value('id'))->filter()->unique();
                PartVehicle::where('part_id', $part->id)->whereNull('car_engine_id')->whereNotIn('car_model_id', $ids)->delete();
                foreach ($ids as $id) {
                    PartVehicle::firstOrCreate(['part_id' => $part->id, 'car_model_id' => $id, 'car_engine_id' => null]);
                }
            }

            $this->command?->info(self::SUPPLIER.": {$added} spark plugs added, {$updated} updated (no stock added).");
        });
    }

    /** Next PRT-00000 number that is not already taken (old imported parts may use some). */
    private function freeSku(): string
    {
        do {
            $sku = Numbering::code('part', 'PRT', 5);
        } while (Part::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
