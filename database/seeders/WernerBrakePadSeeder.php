<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SupplierCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Brake pads from Werner Supplier's price list (rows 53–66).
 *
 * "Buy from supplier when sold" parts: on the website as normal stock, no stock and no stock
 * value here, bought from Werner Supplier only when they sell. Prices are per piece (PC):
 * ours = Werner's price + 70 %, rounded up to the next 500 shillings.
 * Toyota numbering: 04465-… are front pads, 04466-… rear pads.
 * Pads without a picture get the brake pad photo; a picture already on a part is kept.
 *
 *   sudo -u www-data php artisan db:seed --class=WernerBrakePadSeeder --force
 */
class WernerBrakePadSeeder extends Seeder
{
    use SupplierCatalog;

    public const SUPPLIER = 'Werner Supplier';

    /** Name used by the first version of this seeder: its supplier and parts move to Werner. */
    private const OLD_NAME = 'SGS Sales Ltd';

    public const MARKUP = 70;   // %

    /** [list row, part number, original?, cars as on the list, [[make, model], …] for the car finder, Werner price per piece] */
    private const ROWS = [
        [53, '04465-35290', true, '4Runner, FJ Cruiser, Fortuner, Hilux, Hilux Surf, Land Cruiser Prado 120, Land Cruiser Prado 150',
            [['Toyota', 'Fortuner'], ['Toyota', 'Hilux'], ['Toyota', 'Land Cruiser Prado']], 23000],
        [54, '04465-42150', true, 'Blade, Harrier, Lexus RX, Mark X Zio, Matrix, Mirai, Prius Alpha, Prius V, RAV4, Scion iM',
            [['Toyota', 'Harrier'], ['Toyota', 'RAV4']], 19000],
        [55, '04465-42160', true, 'Corolla, Corolla Altis, Corolla Axio, Corolla Fielder, Corolla Rumion, IST, Noah, Voxy, Esquire',
            [['Toyota', 'Corolla'], ['Toyota', 'Corolla Axio'], ['Toyota', 'Rumion'], ['Toyota', 'IST'], ['Toyota', 'Noah'], ['Toyota', 'Voxy']], 19000],
        [56, '04465-42060', false, 'RAV4 old, Camry, Carina, Corolla, Premio, Ipsum',
            [['Toyota', 'RAV4'], ['Toyota', 'Camry'], ['Toyota', 'Corolla'], ['Toyota', 'Premio']], 15000],
        [57, '04465-60230/60340', true, 'Land Cruiser, Land Cruiser 100, Sequoia, Tundra',
            [['Toyota', 'Land Cruiser']], 23000],
        [58, '04466-60040', true, '4Runner, FJ Cruiser, Fortuner, Hilux Surf, Land Cruiser, Land Cruiser 100, Land Cruiser Prado',
            [['Toyota', 'Fortuner'], ['Toyota', 'Land Cruiser'], ['Toyota', 'Land Cruiser Prado']], 20000],
        [59, '04465-48100', true, '', [], 20000],
        [60, '04466-48090', false, 'Harrier', [['Toyota', 'Harrier']], 15000],
        [61, '04465-48090', true, 'Highlander, Kluger', [['Toyota', 'Kluger']], 20000],
        [62, '04466-48020', true, 'Corolla Altis, Caldina, Camry, Harrier, Highlander, Kluger, Windom, Wish',
            [['Toyota', 'Camry'], ['Toyota', 'Harrier'], ['Toyota', 'Kluger'], ['Toyota', 'Wish']], 14000],
        [63, '04465-30330', true, 'Crown, Mark X', [['Toyota', 'Crown'], ['Toyota', 'Mark X']], 19000],
        [64, '04465-30340', true, 'Corolla Altis, Camry, Crown, Crown Athlete, Crown Majesta, Crown Royal Saloon, Isis, Windom, Wish, Yaris / Vios',
            [['Toyota', 'Camry'], ['Toyota', 'Crown'], ['Toyota', 'Wish']], 19000],
        [65, '04466-30230', true, 'Crown, Crown Athlete, Crown Majesta, Crown Royal Saloon, Mark X',
            [['Toyota', 'Crown'], ['Toyota', 'Mark X']], 16000],
        [66, '04466-22180', true, 'Altezza, Aristo, Brevis, Chaser, Cresta, Crown, Crown Estate, Crown Majesta, Mark II, Mark II Wagon',
            [['Toyota', 'Crown'], ['Toyota', 'Mark II']], 15000],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $old = \App\Models\Supplier::where('name', self::OLD_NAME)->first();
            if ($old && ! \App\Models\Supplier::where('name', self::SUPPLIER)->exists()) {
                $old->update(['name' => self::SUPPLIER]);            // same supplier, new name
            }
            $supplier = $this->supplierNamed(self::SUPPLIER);
            if ($old && $old->id !== $supplier->id) {
                // both existed: move the brake pads over so they are not added twice
                \App\Models\Part::where('source_supplier_id', $old->id)->where('subcategory', 'Brake Pads')->update(['source_supplier_id' => $supplier->id]);
            }
            $photo = $this->photo('brake-pad.jpg');
            foreach (self::ROWS as [$row, $no, $original, $fits, $cars, $buy]) {
                $pos = str_starts_with($no, '04466') ? 'Rear' : 'Front';
                $this->upsert($supplier, $no, [
                    'name' => "Brake Pad {$no}".($original ? ' Original' : '')." ({$pos})".($fits !== '' ? ' – Toyota '.strtok($fits, ',') : ''),
                    'brand' => $original ? 'Toyota' : null,
                    'category' => 'Car Parts', 'subcategory' => 'Brake Pads',
                    'purchase_price' => $buy, 'selling_price' => $this->markup($buy, self::MARKUP),
                    'vehicle_make' => 'Toyota',
                    'vehicle_model' => $fits ?: null,
                    'image_path' => $photo,
                    'specs' => array_filter(['Part number' => $no, 'Fitting position' => $pos, 'Quality' => $original ? 'Original' : null,
                        'Fits' => $fits ? 'Toyota '.$fits : null, 'Sold per' => 'Pcs']),
                ], $cars);
            }
            $this->command?->info(self::SUPPLIER.': '.count(self::ROWS).' brake pads added or updated (no stock added, price +'.self::MARKUP.'%).');
        });
    }
}
