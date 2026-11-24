<?php

namespace App\Console\Commands;

use App\Services\OldGarageImport;
use App\Support\XlsxReader;
use Illuminate\Console\Command;
use Throwable;

/**
 * Merge the old AJ LUXE AUTO GARAGE DB workbook into this system (run after import:old-system).
 *
 *   php artisan import:old-garage "C:\Users\YOU\Downloads\AJ_LUXE_AUTO_GARAGE_DB.xlsx"            preview, nothing saved
 *   php artisan import:old-garage "C:\Users\YOU\Downloads\AJ_LUXE_AUTO_GARAGE_DB.xlsx" --commit
 */
class ImportOldGarage extends Command
{
    protected $signature = 'import:old-garage {file : The .xlsx workbook (or a folder of CSV files, one per sheet)} {--commit : Save (without it, only a preview)}';

    protected $description = 'Merge customers, vehicles, mechanics, services, job cards, invoices and payments from the old garage system';

    public function handle(): int
    {
        try {
            $import = new OldGarageImport(XlsxReader::read((string) $this->argument('file')));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Found in the old garage system:');
        $this->table(['What', 'How many'], collect($import->summary)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info($import->check ? 'These do not add up in the old system itself:' : '✓ Every job card and invoice adds up in the old system.');
        foreach ($import->check as $line) {
            $this->line('  '.$line);
        }
        if ($import->merges) {
            $this->info('Customers merged with customers already here:');
            foreach ($import->merges as $m) {
                $this->line('  '.$m);
            }
        }
        foreach (array_unique($import->warnings) as $w) {
            $this->warn('  ! '.$w);
        }

        if (! $this->option('commit')) {
            $this->newLine();
            $this->info('Nothing was saved. Run again with --commit to import.');

            return self::SUCCESS;
        }
        try {
            $diff = $import->commit();
        } catch (Throwable $e) {
            $this->error('Nothing was imported: '.$e->getMessage());

            return self::FAILURE;
        }
        foreach ($diff as $line) {
            $this->warn('  ! '.$line);
        }
        $this->info($diff ? 'Imported, but check the lines above.' : '✓ Imported. Every invoice balance matches the old garage system. New job cards continue the AJ numbering.');

        return self::SUCCESS;
    }
}
