<?php

namespace App\Console\Commands;

use App\Models\CarModel;
use App\Models\ListOption;
use App\Models\Part;
use App\Models\StockMovement;
use App\Support\Audit;
use Illuminate\Console\Command;
use App\Support\OldSystem\MapsOldParts;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Parts sheet exported from the old AJ AUTO PARTS DB (Google Sheets / Excel → CSV).
 *
 *   php artisan import:old-parts "C:\path\AJ_AUTO_PARTS_DB - Parts.csv"            preview only, nothing is saved
 *   php artisan import:old-parts "...Parts.csv" --commit                             save
 *   php artisan import:old-parts "...Parts.csv" --commit --remove-demo               remove the demo parts first, then save
 *
 * Old columns: PartID, PartName, Brand, Category, VehicleMake, VehicleModel, OEMNumber, Unit, Location (pack size),
 * PurchasePrice, SellingPrice, MRP, TaxPct, OpeningStock, CurrentStock, ReorderLevel, Status, ImageURL, Notes …
 *
 * Safe to run again: parts are matched by their old code (PartID), details are updated and stock is never counted twice.
 */
class ImportOldParts extends Command
{
    use MapsOldParts;

    protected $signature = 'import:old-parts {file : CSV of the Parts sheet} {--commit : Save (without it, only a preview)} {--remove-demo : Remove the demo parts first} {--deactivate-others : Switch off parts that are not in this sheet (kept for history, hidden everywhere)}';

    protected $description = 'Import parts from the old AJ AUTO PARTS DB spreadsheet';


    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Cannot open {$file}");

            return self::FAILURE;
        }
        $rows = $this->readCsv($file);
        if (! $rows || ! array_key_exists('partid', $rows[0]) || ! array_key_exists('partname', $rows[0])) {
            $this->error('This does not look like the old Parts sheet (columns PartID and PartName are missing).');

            return self::FAILURE;
        }

        $plan = array_map(fn ($r) => $this->mapPart($r), $rows);
        $plan = array_values(array_filter($plan));
        $this->linkSizes($plan);

        $this->table(['Code', 'Name', 'Department › Type', 'Pack', 'Stock', 'Price', 'Fits'], array_map(fn ($p) => [
            $p['part']['sku'], $p['part']['name'], $p['part']['category'].' › '.$p['part']['subcategory'],
            $p['part']['specs']['Capacity'] ?? '', $p['stock'], number_format($p['part']['selling_price']),
            implode(', ', array_map(fn ($c) => $c[1], $p['cars'])) ?: ($p['part']['vehicle_make'] ?? ''),
        ], $plan));
        foreach (array_unique($this->warnings) as $w) {
            $this->warn('  ! '.$w);
        }

        $codes = array_map(fn ($p) => $p['part']['sku'], $plan);
        $others = Part::where('is_active', true)->whereNotIn('sku', $codes)->where('sku', 'not like', 'DEMO-%')->orderBy('sku')->get(['sku', 'name']);
        if ($others->isNotEmpty()) {
            $this->newLine();
            $this->line($others->count().' other active part(s) are not in this sheet'.($this->option('deactivate-others') ? ' and will be switched off:' : ' (add --deactivate-others to switch them off):'));
            foreach ($others->take(30) as $o) {
                $this->line("    {$o->sku}  {$o->name}");
            }
        }

        if (! $this->option('commit')) {
            $this->newLine();
            $this->info(count($plan).' parts ready. Nothing was saved. Run again with --commit to import them.');

            return self::SUCCESS;
        }

        if ($this->option('remove-demo')) {
            $this->call('demo:remove', ['--force' => true]);
        }

        $created = $updated = 0;
        DB::transaction(function () use ($plan, &$created, &$updated) {
            if (! ListOption::where('list', 'part_category')->where('value', 'Paint & Body')->exists()) {
                ListOption::create(['list' => 'part_category', 'value' => 'Paint & Body', 'sort_order' => (int) ListOption::where('list', 'part_category')->max('sort_order') + 1]);
            }
            $ids = [];
            foreach ($plan as $p) {
                $part = Part::withTrashed()->where('sku', $p['part']['sku'])->first();
                if ($part) {
                    $part->restore();
                    $data = array_diff_key($p['part'], ['variant_of' => 1]);
                    // an earlier plain CSV import put the pack size in the shelf column: clear it
                    if ($p['oldLocation'] !== '' && (string) $part->rack_location === $p['oldLocation']) {
                        $data['rack_location'] = null;
                    }
                    $part->update($data);
                    $updated++;
                } else {
                    $part = Part::create(array_diff_key($p['part'], ['variant_of' => 1]));
                    if ($p['stock'] > 0) {
                        StockMovement::record($part, 'opening', in: $p['stock'], unitCost: (float) $part->purchase_price, note: 'Opening stock from the old system');
                    }
                    $created++;
                }
                $ids[$p['part']['sku']] = $part->id;

                $part->fitments()->delete();
                foreach ($p['cars'] as [$make, $model]) {
                    $car = CarModel::firstOrCreate(['make' => $make, 'model' => $model], ['is_active' => true]);
                    $part->fitments()->firstOrCreate(['car_model_id' => $car->id, 'car_engine_id' => null]);
                }
            }
            if ($this->option('deactivate-others')) {
                Part::where('is_active', true)->whereNotIn('sku', array_keys($ids))->where('sku', 'not like', 'DEMO-%')
                    ->update(['is_active' => false, 'show_online' => false]);
            }
            foreach ($plan as $p) {   // other sizes of the same product
                Part::where('sku', $p['part']['sku'])->update(['variant_of' => $p['part']['variant_of'] ? ($ids[$p['part']['variant_of']] ?? null) : null]);
            }
        });
        Audit::log('Parts', 'IMPORT_OLD', basename((string) $this->argument('file')), "{$created} new, {$updated} updated");
        $this->info("Done: {$created} parts added, {$updated} updated.");

        return self::SUCCESS;
    }

    private function readCsv(string $file): array
    {
        $h = fopen($file, 'r');
        $head = array_map(fn ($c) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c))), fgetcsv($h) ?: []);
        $rows = [];
        while (($r = fgetcsv($h)) !== false) {
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), ''));
        }
        fclose($h);

        return $rows;
    }

}
