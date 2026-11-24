<?php

namespace App\Support;

use App\Models\CarEngine;
use App\Models\CarModel;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * The website's car finder: make → model → engine, only for cars we have parts for.
 * The full Car List stays in "Can't find your car?" and "Import for you".
 */
class ShopVehicles
{
    /** [make => [model, …]] from parts on the website (Car List mapping, plus makes/models typed on parts). */
    public static function all(): array
    {
        $out = [];
        $mapped = CarModel::where('is_active', true)
            ->whereIn('id', DB::table('part_vehicles')->whereIn('part_id', Part::online()->select('id'))->select('car_model_id'))
            ->get(['make', 'model']);
        foreach ($mapped as $c) {
            $out[$c->make][$c->model] = true;
        }
        foreach (Part::online()->whereNotNull('vehicle_make')->where('vehicle_make', '!=', '')->get(['vehicle_make', 'vehicle_model']) as $p) {
            $out[$p->vehicle_make] ??= [];
            foreach (preg_split('/\s*[\/,]\s*/', (string) $p->vehicle_model) as $m) {
                $m = trim(preg_replace('/\s+\d{3,}.*$/', '', $m)); // "Land Cruiser Prado 150" → "Land Cruiser Prado"
                if ($m !== '' && ! str_contains(strtolower($m), 'all ') && ! str_contains(strtolower($m), 'most ')) {
                    $out[$p->vehicle_make][$m] = true;
                }
            }
        }

        // tidy typed names: one spelling per model (Car List spelling wins), no "Subaru" under Subaru,
        // no other make's name under a make, no makes left without a model
        $known = CarModel::where('is_active', true)->get(['make', 'model'])->groupBy(fn ($c) => strtolower($c->make));
        $makes = array_map('strtolower', array_keys($out));
        $clean = [];
        foreach ($out as $make => $models) {
            $spelling = ($known[strtolower($make)] ?? collect())->mapWithKeys(fn ($c) => [strtolower($c->model) => $c->model]);
            foreach (array_keys($models) as $m) {
                $key = strtolower($m);
                $other = collect($makes)->first(fn ($mk) => $mk !== strtolower($make) && str_starts_with($key, $mk.' '));
                if ($key === strtolower($make) || $other) {
                    continue;
                }
                $clean[$make][$key] ??= $spelling[$key] ?? $m;
            }
        }
        ksort($clean);

        return array_map(function ($models) {
            $list = array_values($models);
            natcasesort($list);

            return array_values($list);
        }, $clean);
    }

    /**
     * Engines of a model that we have parts for: [engine id => "3.0 Diesel (1KD-FTV) 2004–2015"].
     * A part linked to "all engines" (or only named on the part) makes every engine of the model count.
     * Empty when the Car List has no engines for the model.
     */
    public static function engines(?string $make, ?string $model): array
    {
        if (! $make || ! $model) {
            return [];
        }
        $car = CarModel::where('is_active', true)->where('make', $make)->where('model', $model)->first();
        if (! $car) {
            return [];
        }
        $fits = DB::table('part_vehicles')->where('car_model_id', $car->id)->whereIn('part_id', Part::online()->select('id'))->pluck('car_engine_id');
        $typed = Part::online()->where('vehicle_make', $make)->where('vehicle_model', 'like', '%'.$model.'%')->exists();
        $everyEngine = $typed || $fits->contains(null);

        return CarEngine::where('car_model_id', $car->id)
            ->when(! $everyEngine, fn ($q) => $q->whereIn('id', $fits->filter()))
            ->orderBy('litres')->orderBy('fuel')->orderBy('year_from')->get()
            ->mapWithKeys(fn (CarEngine $e) => [$e->id => $e->label()])->all();
    }
}
