<?php

namespace Database\Seeders\Concerns;

use App\Models\CarModel;
use App\Models\Part;
use App\Models\PartVehicle;
use App\Models\Supplier;
use App\Support\Numbering;

/**
 * Shared by the supplier price-list seeders (Kitonga Spareparts, SGS Sales, …): parts bought
 * from the supplier only when they sell (no stock, no stock value), on the website as normal stock.
 */
trait SupplierCatalog
{
    protected function supplierNamed(string $name): Supplier
    {
        return Supplier::firstOrCreate(['name' => $name], ['code' => Numbering::code('supplier', 'SUP'), 'is_active' => true]);
    }

    protected function kitonga(): Supplier
    {
        return $this->supplierNamed('Kitonga Spareparts');
    }

    /** Price + a percentage, rounded up to the next 500 shillings. */
    protected function markup(float $price, float $percent): float
    {
        return (float) (ceil($price * (1 + $percent / 100) / 500) * 500);
    }

    /** Kitonga: their selling price + 15 %, rounded up to 500. Never below their buying price + 15 %. */
    protected function ourPrice(float $buy, float $sell): float
    {
        return $this->markup(max($sell, $buy), 15);
    }

    /** Create or update one supplier part (matched on part number + supplier), then link the cars. */
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
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($path) && ! $disk->put($path, file_get_contents(dirname(__DIR__).'/assets/'.$file))) {
            // usually the storage folder belongs to the web server user: run the seeder as that user
            throw new \RuntimeException("Could not save the picture to storage/app/public/{$path}. "
                .'Run the seeder as the web server user, e.g.: sudo -u www-data php artisan db:seed --class='.class_basename(static::class).' --force');
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
