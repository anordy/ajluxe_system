<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\KitongaSupplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Spark plugs from Kitonga Spareparts' price list (rows 160–171).
 *
 * They are added as "buy from supplier when sold" parts: on the website as normal stock,
 * no stock and no stock value here, bought from Kitonga only when they sell.
 * Prices on the list: Kitonga's buying and selling price. Ours = their selling price + 15 %.
 * Plugs without a picture get the spark plug photo; a picture already on a plug is kept.
 * Safe to run again: existing rows (same part number from Kitonga) are updated, not doubled.
 *
 *   php artisan db:seed --class=KitongaSparkPlugSeeder --force
 */
class KitongaSparkPlugSeeder extends Seeder
{
    use KitongaSupplier;

    public const SUPPLIER = 'Kitonga Spareparts';

    /**
     * [part number, brand, name, fits as written on the list, [[make, model], …] for the car finder, Kitonga buying, Kitonga selling, pack]
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
            $supplier = $this->kitonga();
            $photo = $this->photo('kitonga-spark-plug.jpg');       // only for plugs without a picture yet
            foreach (self::ROWS as [$no, $brand, $name, $fits, $cars, $buy, $sell, $pack]) {
                $makes = collect($cars)->pluck(0)->unique();
                $this->upsert($supplier, $no, [
                    'name' => $name, 'brand' => $brand, 'category' => 'Car Parts', 'subcategory' => 'Spark Plugs',
                    'purchase_price' => $buy, 'selling_price' => $this->ourPrice($buy, $sell),
                    'vehicle_make' => $makes->count() === 1 ? $makes->first() : null,
                    'vehicle_model' => $fits,
                    'specs' => array_filter(['Part number' => $no, 'Brand' => $brand, 'Fits' => $fits, 'Pack' => $pack ? $pack.' plugs' : null]),
                    'image_path' => $photo,
                ], $cars);
            }
            $this->command?->info('Kitonga Spareparts: '.count(self::ROWS).' spark plugs added or updated (no stock added, price +15%).');
        });
    }
}
