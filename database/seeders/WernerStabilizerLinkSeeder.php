<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SupplierCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Stabilizer links from Werner Supplier's price list (rows 26–51).
 *
 * "Buy from supplier when sold" parts: on the website as normal stock, no stock and no stock
 * value here, bought from Werner only when they sell. Prices are per piece (PC):
 * ours = Werner's price + 70 %, rounded up to the next 500 shillings.
 * Links without a picture get the stabilizer link photo; a picture already on a part is kept.
 *
 *   sudo -u www-data php artisan db:seed --class=WernerStabilizerLinkSeeder --force
 */
class WernerStabilizerLinkSeeder extends Seeder
{
    use SupplierCatalog;

    public const SUPPLIER = 'Werner Supplier';

    public const MARKUP = 70;   // %

    /** [list row, part number, side, make, cars as on the list, [[make, model], …] for the car finder, Werner price per piece] */
    private const ROWS = [
        [26, '48819-52010', null, 'Toyota', 'Funcargo, IST, Platz, Porte, Probox, Raum, Scion xA, Scion xB, Sienta, Soluna Vios, Vitz, Will Cypha',
            [['Toyota', 'IST'], ['Toyota', 'Porte'], ['Toyota', 'Probox'], ['Toyota', 'Raum'], ['Toyota', 'Vitz']], 4000],
        [27, '48820-47010', null, 'Toyota', 'Allion, Alphard, Avensis, Caldina, Corolla, Corolla Altis, Corolla Axio, Corolla Fielder, Corolla HB',
            [['Toyota', 'Allion'], ['Toyota', 'Alphard'], ['Toyota', 'Corolla'], ['Toyota', 'Corolla Axio']], 6000],
        [28, '48820-42020', null, 'Toyota', 'Celica, Corolla, Corolla HB, Opa, RAV4, Vista, Vista Ardeo',
            [['Toyota', 'Corolla'], ['Toyota', 'Opa'], ['Toyota', 'RAV4']], 6000],
        [29, '48840-42010', null, 'Toyota', 'RAV4', [['Toyota', 'RAV4']], 6000],
        [30, '48830-42010', null, 'Toyota', 'RAV4', [['Toyota', 'RAV4']], 6000],
        [31, '48820-42010', null, 'Toyota', 'RAV4', [['Toyota', 'RAV4']], 6000],
        [32, '48820-42030', null, 'Toyota', 'Alphard, Auris, Avensis, Blade, Corolla, Corolla Rumion, Estima, Estima Emina, Harrier, Mark X Zio',
            [['Toyota', 'Alphard'], ['Toyota', 'Corolla'], ['Toyota', 'Rumion'], ['Toyota', 'Harrier']], 7000],
        [33, '48820-52030', null, 'Toyota', 'Corolla Axio, Corolla Fielder, IST, Probox, Ractis, Sienta, Urban Cruiser, Vios',
            [['Toyota', 'Corolla Axio'], ['Toyota', 'IST'], ['Toyota', 'Probox']], 7000],
        [34, '48820-28021', null, 'Toyota', 'Lite Ace / Town Ace, Lite Ace Noah, Town Ace / Master Ace', [], 6000],
        [35, '48820-22040', null, 'Toyota', 'Altezza, Brevis, Chaser, Cresta, Crown, Crown Majesta, Mark II, Mark II Wagon Blit, Origin, Progres',
            [['Toyota', 'Crown'], ['Toyota', 'Mark II']], 7000],
        [36, '48820-0K030', null, 'Toyota', '4Runner, FJ Cruiser, Fortuner, Hilux, Hilux Surf, Land Cruiser Prado 120, Land Cruiser Prado 150',
            [['Toyota', 'Fortuner'], ['Toyota', 'Hilux'], ['Toyota', 'Land Cruiser Prado']], 7000],
        [37, '48810-0K010', null, 'Toyota', '4Runner, FJ Cruiser, Fortuner, Hilux, Hilux Surf, Land Cruiser Prado 120, Land Cruiser Prado 150',
            [['Toyota', 'Fortuner'], ['Toyota', 'Hilux'], ['Toyota', 'Land Cruiser Prado']], 7000],
        [38, '48820-35030', null, 'Toyota', '4Runner, Hilux Surf, Land Cruiser Prado, Tundra', [['Toyota', 'Land Cruiser Prado']], 6000],
        [39, '48830-35020', null, 'Toyota', '4Runner, Hilux Surf, Land Cruiser Prado', [['Toyota', 'Land Cruiser Prado']], 6000],
        [40, '48810-30070', 'Left', 'Toyota', 'Crown, Crown Athlete, Crown Majesta, Crown Royal Saloon, Mark X',
            [['Toyota', 'Crown'], ['Toyota', 'Mark X']], 7000],
        [41, '48820-30090', null, 'Toyota', 'Alphard, Avalon, Camry, Estima, Estima Emina, Harrier, Highlander, Kluger, Previa, RAV4, RAV4 PHV',
            [['Toyota', 'Alphard'], ['Toyota', 'Camry'], ['Toyota', 'Harrier'], ['Toyota', 'Kluger'], ['Toyota', 'RAV4']], 7000],
        [42, '48830-48010', null, 'Toyota', 'Avalon, Camry, Harrier, Highlander, Kluger, Venza',
            [['Toyota', 'Camry'], ['Toyota', 'Harrier'], ['Toyota', 'Kluger']], 6000],
        [43, '48830-33010', null, 'Toyota', 'Harrier, Windom, Vista, Scepter, Pronard, Mark II Wagon Qualis, Mark II',
            [['Toyota', 'Harrier'], ['Toyota', 'Mark II']], 6000],
        [44, '48830-51010', null, 'Toyota', 'Altezza, Brevis, Crown, Crown Majesta, Mark II, Mark II Wagon Blit, Origin, Progres, Verossa',
            [['Toyota', 'Crown'], ['Toyota', 'Mark II']], 8000],
        [45, '48830-32050', null, 'Toyota', '', [], 6000],
        [46, '20470-SA011', null, 'Subaru', 'Forester, Impreza WRX STI', [['Subaru', 'Forester'], ['Subaru', 'Impreza']], 6000],
        [47, '20470-AJ010', null, 'Subaru', '', [], 9000],
        [48, '54668-JD00A', 'Left', 'Nissan', 'Dualis, X-Trail', [['Nissan', 'Dualis'], ['Nissan', 'X-Trail']], 7000],
        [49, '54618-JD00A', 'Right', 'Nissan', 'Dualis, X-Trail', [['Nissan', 'Dualis'], ['Nissan', 'X-Trail']], 7000],
        [50, '54618-8H300', null, 'Nissan', 'X-Trail', [['Nissan', 'X-Trail']], 6000],
        [51, '54668-8H300', null, 'Nissan', 'X-Trail', [['Nissan', 'X-Trail']], 6000],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $supplier = $this->supplierNamed(self::SUPPLIER);
            $photo = $this->photo('stabilizer-link.jpg');
            foreach (self::ROWS as [$row, $no, $side, $make, $fits, $cars, $buy]) {
                $this->upsert($supplier, $no, [
                    'name' => "Stabilizer Link {$no}".($side ? " ({$side})" : '').' – '.$make.($fits !== '' ? ' '.strtok($fits, ',') : ''),
                    'brand' => null, 'category' => 'Car Parts', 'subcategory' => 'Stabilizer Links',
                    'purchase_price' => $buy, 'selling_price' => $this->markup($buy, self::MARKUP),
                    'vehicle_make' => $make,
                    'vehicle_model' => $fits ?: null,
                    'image_path' => $photo,
                    'specs' => array_filter(['Part number' => $no, 'Side' => $side, 'Fits' => $fits ? $make.' '.$fits : null, 'Sold per' => 'Pcs']),
                ], $cars);
            }
            $this->command?->info(self::SUPPLIER.': '.count(self::ROWS).' stabilizer links added or updated (no stock added, price +'.self::MARKUP.'%).');
        });
    }
}
