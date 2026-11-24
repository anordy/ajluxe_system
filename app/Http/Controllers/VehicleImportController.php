<?php

namespace App\Http\Controllers;

use App\Models\CarEngine;
use App\Models\CarModel;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Car list CSV import (plain form upload): make, model, engine_code, litres, fuel, year_from, year_to.
 * Adds what is missing and never deletes, so a bought vehicle list can be loaded on top of the starter list.
 */
class VehicleImportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->hasRole('admin', 'manager'), 403);
        $back = redirect()->route('website.cars');
        $file = $request->file('file');
        if (! $file || ! $file->isValid() || ! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return $back->with('toast', ['message' => 'Upload a .csv file.', 'type' => 'error']);
        }

        $h = fopen($file->getRealPath(), 'r');
        $first = fgetcsv($h) ?: [];
        $cols = array_map(fn ($c) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c))), $first);
        $hasHeader = in_array('make', $cols, true);
        $map = $hasHeader ? array_flip($cols) : ['make' => 0, 'model' => 1, 'engine_code' => 2, 'litres' => 3, 'fuel' => 4, 'year_from' => 5, 'year_to' => 6];
        $rows = $hasHeader ? [] : [$first];
        while (($r = fgetcsv($h)) !== false) {
            $rows[] = $r;
        }
        fclose($h);

        $cars = $engines = $skipped = 0;
        $get = fn ($r, $k) => isset($map[$k]) ? trim((string) ($r[$map[$k]] ?? '')) : '';
        foreach ($rows as $r) {
            $make = $get($r, 'make');
            $model = $get($r, 'model');
            if ($make === '' || $model === '' || mb_strlen($make) > 60 || mb_strlen($model) > 80) {
                $skipped++;
                continue;
            }
            $car = CarModel::firstOrCreate(['make' => $make, 'model' => $model], ['is_active' => true]);
            $cars += $car->wasRecentlyCreated ? 1 : 0;

            $code = strtoupper($get($r, 'engine_code')) ?: null;
            $litres = is_numeric(str_replace(',', '.', $get($r, 'litres'))) ? round((float) str_replace(',', '.', $get($r, 'litres')), 1) : null;
            if ($code || $litres) {
                $fuel = collect(CarEngine::FUELS)->first(fn ($f) => strcasecmp($f, $get($r, 'fuel')) === 0) ?? 'Petrol';
                $year = fn ($v) => preg_match('/^\d{4}$/', $v) ? (int) $v : null;
                $e = CarEngine::firstOrCreate(['car_model_id' => $car->id, 'code' => $code, 'litres' => $litres, 'fuel' => $fuel],
                    ['year_from' => $year($get($r, 'year_from')), 'year_to' => $year($get($r, 'year_to'))]);
                $engines += $e->wasRecentlyCreated ? 1 : 0;
            }
        }
        Audit::log('Website', 'CARS_IMPORT', "$cars cars, $engines engines");

        return $back->with('toast', ['message' => "Imported: {$cars} new cars, {$engines} new engines".($skipped ? ", {$skipped} rows skipped (no make or model)." : '.'), 'type' => 'success']);
    }
}
