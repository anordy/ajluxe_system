<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * AJ LUXE AUTO garage paint price list (TZS) → Services.
 * Safe to run again: it updates prices by service code and never duplicates.
 *
 *   php artisan db:seed --class=GarageTariffSeeder
 *
 * Codes (the website reads them to build the price list and calculator):
 *   PNT-{part}-{W|S}{I|E}   W = workshop, S = spray booth, I = our painter, E = outside painter
 *   PNT-J24-.. / PNT-J5-.. / PNT-FULL-..   jobs with 2–4 parts / 5+ parts / full car
 *   PNT-MIR, PNT-RIM, PNT-RIM4, PNT-POLH, PNT-POLF   same price for everyone
 */
class GarageTariffSeeder extends Seeder
{
    public const PARTS = [
        'FB' => 'Front bumper', 'BB' => 'Back bumper', 'BN' => 'Bonnet', 'RF' => 'Roof',
        'BT' => 'Boot lid (trunk)', 'DR' => 'Door', 'FD' => 'Fender', 'MG' => 'Mudguard',
    ];

    public const JOBS = ['J24' => '2 – 4 parts', 'J5' => '5+ parts', 'FULL' => 'Full car'];

    public const AREAS = ['W' => 'Workshop', 'S' => 'Spray booth'];
    public const PAINTERS = ['I' => 'our painter', 'E' => 'outside painter'];

    /** [workshop IP, workshop EP, spray booth IP, spray booth EP] */
    private const PRICES = [
        'FB' => [15000, 20000, 20000, 25000], 'BB' => [15000, 20000, 20000, 25000],
        'BN' => [15000, 20000, 20000, 25000], 'RF' => [15000, 20000, 20000, 25000],
        'BT' => [15000, 20000, 20000, 25000], 'DR' => [15000, 20000, 20000, 25000],
        'FD' => [10000, 15000, 15000, 20000], 'MG' => [10000, 15000, 15000, 20000],
        'J24' => [30000, 35000, 40000, 50000], 'J5' => [50000, 60000, 70000, 90000],
        'FULL' => [60000, 70000, 80000, 100000],
    ];

    private const FLAT = [
        'PNT-MIR'  => ['Paint: Side mirror (each)', 5000],
        'PNT-RIM'  => ['Paint: Rim (each)', 5000],
        'PNT-RIM4' => ['Paint: Rims (all 4)', 15000],
        'PNT-POLH' => ['Car polish (half car)', 10000],
        'PNT-POLF' => ['Car polish (full car)', 15000],
    ];

    public function run(): void
    {
        $columns = ['WI', 'WE', 'SI', 'SE'];
        foreach (self::PRICES as $key => $prices) {
            $what = self::PARTS[$key] ?? self::JOBS[$key];
            foreach ($columns as $i => $col) {
                $name = 'Paint: '.$what.' — '.self::AREAS[$col[0]].', '.self::PAINTERS[$col[1]];
                $this->put("PNT-{$key}-{$col}", $name, $prices[$i]);
            }
        }
        foreach (self::FLAT as $code => [$name, $price]) {
            $this->put($code, $name, $price);
        }
    }

    private function put(string $code, string $name, int $rate): void
    {
        Service::updateOrCreate(['code' => $code], [
            'name' => $name, 'category' => 'Body & Painting', 'pricing' => 'fixed', 'rate' => $rate, 'est_hours' => 0, 'is_active' => true,
        ]);
    }
}
