<?php

namespace Database\Seeders\Concerns;

use App\Models\CarModel;
use App\Models\Part;
use App\Models\PartVehicle;
use App\Models\Supplier;
use App\Support\Numbering;

/**
 * Shared by the Kitonga Spareparts seeders: parts bought from Kitonga only when they sell
 * (no stock, no stock value), on the website as normal stock, with our price = Kitonga's
 * selling price + 15 %, rounded up to the next 500 shillings.
 */
trait KitongaSupplier
{
    protected function kitonga(): Supplier
    {
        return Supplier::firstOrCreate(['name' => 'Kitonga Spareparts'], ['code' => Numbering::code('supplier', 'SUP'), 'is_active' => true]);
    }

    /** Kitonga's selling price + 15 %, rounded up to 500. Never below their buying price + 15 %. */
    protected function ourPrice(float $buy, float $sell): float
    {
        $base = max($sell, $buy);

        return (float) (ceil($base * 1.15 / 500) * 500);
    }

    /** Create or update one Kitonga part (matched on part number + name), then link the cars. */
    protected function upsert(Supplier $supplier, string $partNo, array $values, array $cars): Part
    {
        $values += ['is_active' => true, 'show_online' => true, 'sourced' => true, 'source_supplier_id' => $supplier->id, 'unit' => 'Pcs'];
        $part = Part::where('oem_no', $partNo)->where('source_supplier_id', $supplier->id)->first();
        if ($part) {
            // never replace a picture already on the part (one uploaded in the staff portal stays)
            if ($part->image_path) {
                unset($values['image_path']);
            }
            $part->update($values);
        } else {
            $part = Part::create($values + ['oem_no' => $partNo, 'sku' => $this->freeSku()]);
        }

        // link to the website's car finder where the car is on the car list
        $ids = collect($cars)->map(fn ($c) => CarModel::where('make', $c[0])->where('model', $c[1])->value('id'))->filter()->unique();
        PartVehicle::where('part_id', $part->id)->whereNull('car_engine_id')->whereNotIn('car_model_id', $ids)->delete();
        foreach ($ids as $id) {
            PartVehicle::firstOrCreate(['part_id' => $part->id, 'car_model_id' => $id, 'car_engine_id' => null]);
        }

        return $part;
    }

    /** Copy the seeder's photo to the website's storage (once) and return its path. */
    protected function photo(string $file): string
    {
        $path = 'parts/'.$file;
        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, file_get_contents(dirname(__DIR__).'/assets/'.$file));
        }

        return $path;
    }

    /** Next PRT-00000 number that is not already taken (old imported parts may use some). */
    protected function freeSku(): string
    {
        do {
            $sku = Numbering::code('part', 'PRT', 5);
        } while (Part::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
