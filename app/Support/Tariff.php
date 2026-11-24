<?php

namespace App\Support;

use App\Models\Service;
use Database\Seeders\GarageTariffSeeder;

/**
 * The paint price list as the website shows it, read from Services (codes PNT-…),
 * so changing a price in Services changes the website.
 */
class Tariff
{
    public const COLUMNS = ['WI' => 'Workshop, our painter', 'WE' => 'Workshop, outside painter', 'SI' => 'Spray booth, our painter', 'SE' => 'Spray booth, outside painter'];

    public static function load(): array
    {
        $rates = Service::where('code', 'like', 'PNT-%')->where('is_active', true)->pluck('rate', 'code')->map(fn ($r) => (int) $r);

        $grid = fn (array $keys) => collect($keys)->map(fn ($label, $key) => ['key' => $key, 'label' => $label]
            + collect(array_keys(self::COLUMNS))->mapWithKeys(fn ($col) => [$col => $rates["PNT-{$key}-{$col}"] ?? null])->all())
            ->filter(fn ($row) => collect(array_keys(self::COLUMNS))->contains(fn ($c) => $row[$c] !== null))->values()->all();

        $flat = collect([
            'PNT-MIR' => 'Side mirror (each)', 'PNT-RIM' => 'Rim (each)', 'PNT-RIM4' => 'Rims (all 4)',
            'PNT-POLH' => 'Car polish (half car)', 'PNT-POLF' => 'Car polish (full car)',
        ])->filter(fn ($l, $code) => isset($rates[$code]))->map(fn ($l, $code) => ['code' => $code, 'label' => $l, 'price' => $rates[$code]])->values()->all();

        $parts = $grid(GarageTariffSeeder::PARTS);

        return [
            'parts'   => $parts,
            'jobs'    => collect($grid(GarageTariffSeeder::JOBS))->keyBy('key')->all(),
            'flat'    => $flat,
            'columns' => self::COLUMNS,
            'from'    => collect($parts)->pluck('WI')->filter()->min(),          // cheapest single part, our painter
            'boothFrom' => collect($parts)->pluck('SI')->filter()->min(),
            'has'     => $parts !== [],
        ];
    }
}
