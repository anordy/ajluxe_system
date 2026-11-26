<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SupplierCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ball joints from Werner Supplier's price list (rows 78–82).
 *
 * "Buy from supplier when sold" parts: on the website as normal stock, no stock and no stock
 * value here, bought from Werner only when they sell. Prices are per piece (PC):
 * ours = Werner's price + 70 %, rounded up to the next 500 shillings.
 * Ball joints without a picture get the ball joint photo; a picture already on a part is kept.
 *
 *   sudo -u www-data php artisan db:seed --class=WernerBallJointSeeder --force
 */
class WernerBallJointSeeder extends Seeder
{
    use SupplierCatalog;

    public const SUPPLIER = 'Werner Supplier';

    public const MARKUP = 70;   // %

    /** [list row, part number, maker's code, cars as on the list, [[make, model], …] for the car finder, Werner price per piece] */
    private const ROWS = [
        [78, '43308-59035', null, '', [], 8000],
        [79, '43330-19115', 'CBT-46', 'Corolla NZE120 / NZE121, Corolla new, Wish, RAV4 (kill time)',
            [['Toyota', 'Corolla'], ['Toyota', 'Wish'], ['Toyota', 'RAV4']], 10000],
        [80, '43330-39585', null, '', [], 30000],
        [81, '43340-39465', null, '', [], 30000],
        [82, '43310-39016', null, '', [], 8000],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $supplier = $this->supplierNamed(self::SUPPLIER);
            $photo = $this->photo('ball-joint.jpg');
            foreach (self::ROWS as [$row, $no, $code, $fits, $cars, $buy]) {
                $this->upsert($supplier, $no, [
                    'name' => "Ball Joint {$no}".($code ? " ({$code})" : '').' – Toyota'.($fits !== '' ? ' '.strtok($fits, ',/') : ''),
                    'brand' => null, 'category' => 'Car Parts', 'subcategory' => 'Ball Joints',
                    'purchase_price' => $buy, 'selling_price' => $this->markup($buy, self::MARKUP),
                    'vehicle_make' => 'Toyota',
                    'vehicle_model' => $fits ?: null,
                    'image_path' => $photo,
                    'specs' => array_filter(['Part number' => $no, 'Maker code' => $code, 'Fits' => $fits ? 'Toyota '.$fits : null, 'Sold per' => 'Pcs']),
                ], $cars);
            }
            $this->command?->info(self::SUPPLIER.': '.count(self::ROWS).' ball joints added or updated (no stock added, price +'.self::MARKUP.'%).');
        });
    }
}
