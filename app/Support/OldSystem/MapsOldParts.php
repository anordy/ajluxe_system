<?php

namespace App\Support\OldSystem;

/**
 * How a row of the old AJ AUTO PARTS DB "Parts" sheet becomes a part in this system
 * (departments, pack sizes, viscosity, cars from Notes, other sizes of the same oil).
 * Used by "import:old-parts" (Parts sheet only) and "import:old-system" (the whole workbook).
 */
trait MapsOldParts
{
    public array $warnings = [];

    /** Car names written in the old Notes column → [make, model]. Spelling mistakes included. */
    public const CARS = [
        '/\bIST\b/' => ['Toyota', 'IST'],
        '/\bVIT[SZ]\b/' => ['Toyota', 'Vitz'],
        '/RAUM/' => ['Toyota', 'Raum'],
        '/\bPORTE/' => ['Toyota', 'Porte'],
        '/\bHARRIER\b/' => ['Toyota', 'Harrier'],
        '/\bKLUGER\b/' => ['Toyota', 'Kluger'],
        '/\bCOROLLA\b/' => ['Toyota', 'Corolla'],
        '/\bAXIO\b/' => ['Toyota', 'Corolla Axio'],
        '/\bRAV[\s-]?4\b/' => ['Toyota', 'RAV4'],
        '/\bALPHARD\b/' => ['Toyota', 'Alphard'],
        '/\bWISH\b/' => ['Toyota', 'Wish'],
        '/\bAL+L?ION\b|\bALION\b/' => ['Toyota', 'Allion'],
        '/\bCAMRY\b/' => ['Toyota', 'Camry'],
        '/\bVIG[O0]\b/' => ['Toyota', 'Hilux'],
        '/\bF[OU]RTUNER\b/' => ['Toyota', 'Fortuner'],
        '/\bPRADO\b/' => ['Toyota', 'Land Cruiser Prado'],
        '/\bLAND\s+CRU[IS]+ER\b/' => ['Toyota', 'Land Cruiser'],
        '/\bCOASTER\b/' => ['Toyota', 'Coaster'],
        '/\bDYNA\b/' => ['Toyota', 'Dyna'],
        '/\bMARK\s*(11|II)\b/' => ['Toyota', 'Mark II'],
        '/\bMARK\s*X\b/' => ['Toyota', 'Mark X'],
        '/\bCROWN\b/' => ['Toyota', 'Crown'],
        '/\bRUMION\b/' => ['Toyota', 'Rumion'],
        '/\bPASSO\b/' => ['Toyota', 'Passo'],
        '/\bPREMIO\b/' => ['Toyota', 'Premio'],
        '/\bPROBOX\b/' => ['Toyota', 'Probox'],
        '/\bNOAH\b/' => ['Toyota', 'Noah'],
        '/\bVOXY\b/' => ['Toyota', 'Voxy'],
        '/\bOPA\b/' => ['Toyota', 'Opa'],
        '/\bVANGUARD\b/' => ['Toyota', 'Vanguard'],
        '/\bCX[\s-]?5\b/' => ['Mazda', 'CX-5'],
        '/\bFOREST(ER)?\b/' => ['Subaru', 'Forester'],
        '/\bX[\s-]?TRAIL\b/' => ['Nissan', 'X-Trail'],
        '/\bDUALIS\b/' => ['Nissan', 'Dualis'],
        '/\bMARCH\b/' => ['Nissan', 'March'],
    ];

    /** Words in Notes that are not car names (ignored when looking for unknown cars). */
    public const NOT_CARS = ['OLD', 'NEW', 'ETC', 'NISSAN', 'MAZDA', 'SUBARU', 'TOYOTA', 'MITSUBISHI', '120', 'AND'];

    /** One old row → the new part, its opening stock and the cars it fits. */
    public function mapPart(array $r): ?array
    {
        $t = fn ($k) => trim(preg_replace('/\s+/', ' ', (string) ($r[$k] ?? '')));
        $n = fn ($k) => (float) str_replace([',', ' '], '', $t($k));
        $code = strtoupper($t('partid'));
        $name = $t('partname');
        if ($code === '' || $name === '') {
            $this->warnings[] = 'A row without PartID or PartName was skipped.';

            return null;
        }
        $oldCat = strtolower($t('category'));
        $unit = $t('unit') ?: 'Pcs';
        $pack = $n('location');
        $notes = $t('notes');
        $specs = [];
        $up = strtoupper($name);

        // department and type
        [$cat, $sub] = match (true) {
            str_contains($up, 'COOLANT')                                     => ['Car Parts', 'Coolant'],
            $oldCat === 'lubricants' && preg_match('/CVT|T-?IV|ATF|TYPE/', $up) => ['Lubricants', 'Transmission'],
            $oldCat === 'lubricants'                                         => ['Lubricants', 'Engine Oil'],
            $oldCat === 'paint'                                              => ['Paint & Body', match (true) {
                str_contains($up, 'CLEAR') => 'Clear Coat', str_contains($up, 'THINNER') => 'Thinner', str_contains($up, 'HARDENER') => 'Hardener', default => 'Paint',
            }],
            $oldCat === 'batteries'                                          => ['Car Parts', 'Batteries'],
            str_starts_with($up, 'OIL FILTER')                               => ['Filters', 'Oil Filter'],
            str_starts_with($up, 'AIR FILTER')                               => ['Filters', 'Air Filter'],
            str_starts_with($up, 'AC FILTER'), str_contains($up, 'CABIN')     => ['Filters', 'Cabin Filter'],
            default                                                          => [ucwords($oldCat) ?: 'Car Parts', null],
        };

        // pack size: the old "Location" column held the litres in a bottle
        if (in_array(strtolower($unit), ['litre', 'liter', 'ltr', 'l'], true)) {
            $litres = preg_match('/(\d+(?:\.\d+)?)\s*L\b/i', $name, $m) ? (float) $m[1] : $pack;
            if ($litres > 0) {
                $specs['Capacity'] = rtrim(rtrim(number_format($litres, 1), '0'), '.').' L';
            }
            $unit = 'Pcs';
        } elseif ($pack > 1 && $oldCat !== 'batteries') {
            $this->warnings[] = "{$code}: Location “{$pack}” is not a pack size for {$unit}, ignored.";
        }
        if (preg_match('/\b(\d{1,2}W-?\d{2})\b/i', $name, $m)) {
            $specs['Viscosity'] = strtoupper(str_contains($m[1], '-') ? $m[1] : preg_replace('/W/i', 'W-', $m[1]));
        }
        if ($sub === 'Transmission') {
            $specs['Oil type'] = str_contains($up, 'CVT') ? 'CVT fluid' : 'Automatic transmission fluid';
            $specs['Specification'] = $notes ?: null;
        }
        if ($sub === 'Batteries') {
            if (preg_match('/\b(N\d{2,3})\s*([RL])\b/', $up, $m)) {
                $specs['Battery size'] = $m[1];
                $specs['Terminal layout'] = $m[2] === 'R' ? 'Right +' : 'Left +';
            }
            if (str_contains($up, 'OXIDE')) {
                $this->warnings[] = "{$code}: brand “{$t('brand')}” — did you mean Chloride Exide? Imported as written.";
            }
        }
        if ($t('oemnumber') !== '') {
            $specs['Part number'] = $t('oemnumber');
        }

        // cars it fits: from Notes (filters list car names there)
        $cars = [];
        $make = $t('vehiclemake');
        $isList = in_array($cat, ['Filters', 'Car Parts'], true) && $sub !== 'Coolant';
        if ($isList && $notes !== '') {
            $U = strtoupper($notes);
            foreach (self::CARS as $re => $car) {
                if (preg_match($re, $U)) {
                    $cars[$car[0].'|'.$car[1]] = $car;
                }
            }
            $left = preg_replace(array_keys(self::CARS), ' ', $U);
            $unknown = array_diff(array_filter(preg_split('/[^A-Z0-9-]+/', $left), fn ($w) => strlen($w) > 1), self::NOT_CARS);
            if ($unknown) {
                $this->warnings[] = "{$code}: car name(s) not recognised in Notes: ".implode(', ', $unknown).' (kept as text)';
            }
        }
        if (in_array(strtolower($make), ['royal enfield'], true)) {
            $this->warnings[] = "{$code}: “{$make}” is a motorbike make — check this part.";
        }

        $selling = $n('sellingprice');
        $mrp = $n('mrp');

        return [
            'stock' => max(0, $n('currentstock')),
            'oldLocation' => $t('location'),
            'cars'  => array_values($cars),
            'part'  => [
                'sku'               => $code,
                'name'              => $name,
                'brand'             => $t('brand') ?: null,
                'category'          => $cat,
                'subcategory'       => $sub,
                'oem_no'            => $t('oemnumber') ?: null,
                'unit'              => $unit,
                'vehicle_make'      => strcasecmp($make, 'Universal') === 0 || $make === '' ? null : $make,
                'vehicle_model'     => $isList && $notes !== '' ? mb_convert_case(mb_strtolower($notes), MB_CASE_TITLE) : ($t('vehiclemodel') ?: null),
                'purchase_price'    => $n('purchaseprice'),
                'selling_price'     => $selling,
                'min_selling_price' => 0,
                'tax_rate'          => $n('taxpct'),
                'reorder_level'     => $n('reorderlevel'),
                'is_active'         => strcasecmp($t('status'), 'Inactive') !== 0,
                'show_online'       => $selling > 0,
                'discount_percent'  => 0,
                'web_description'   => ! $isList && $notes !== '' && $sub !== 'Transmission' ? $notes : null,
                'specs'             => array_filter($specs) ?: null,
                'variant_of'        => null,
            ] + ($mrp > $selling ? ['_mrp' => $mrp] : []),
        ];
    }

    /** "Engine Oil 5W-30 5L" and "Engine Oil 5W-30 1L" → one product; the biggest pack is the main one. */
    public function linkSizes(array &$plan): void
    {
        $groups = [];
        foreach ($plan as $i => $p) {
            if (isset($p['part']['specs']['Capacity'])) {
                $base = strtolower(trim(preg_replace('/\b\d+(\.\d+)?\s*L\b/i', '', $p['part']['name'])));
                $groups[$p['part']['category'].'|'.$base][] = $i;
            }
        }
        foreach ($groups as $idx) {
            if (count($idx) < 2) {
                continue;
            }
            usort($idx, fn ($a, $b) => (float) $plan[$b]['part']['specs']['Capacity'] <=> (float) $plan[$a]['part']['specs']['Capacity']);
            $main = $plan[$idx[0]]['part']['sku'];
            foreach (array_slice($idx, 1) as $i) {
                $plan[$i]['part']['variant_of'] = $main;
            }
        }
        foreach ($plan as &$p) {
            unset($p['part']['_mrp']);
        }
    }
}
