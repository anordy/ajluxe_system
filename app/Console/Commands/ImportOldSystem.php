<?php

namespace App\Console\Commands;

use App\Services\OldSystemImport;
use App\Support\XlsxReader;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bring everything across from the old AJ AUTO PARTS DB workbook.
 *
 *   php artisan import:old-system "C:\Users\YOU\Downloads\AJ_AUTO_PARTS_DB.xlsx"                    preview, nothing saved
 *   php artisan import:old-system "C:\Users\YOU\Downloads\AJ_AUTO_PARTS_DB.xlsx" --commit --fresh      clean system first, then import
 *
 * You can also give a folder that holds every sheet exported as CSV.
 */
class ImportOldSystem extends Command
{
    protected $signature = 'import:old-system {file : The .xlsx workbook (or a folder of CSV files, one per sheet)}
        {--commit : Save (without it, only a preview)}
        {--remove-demo : Remove the demo parts first}
        {--fresh : Delete ALL existing parts, customers, job cards, sales, purchases and other test data first (a backup is made)}';

    protected $description = 'Import parts, suppliers, customers, purchases, sales, payments and stock history from the old AJ AUTO PARTS DB';

    public function handle(): int
    {
        try {
            $import = new OldSystemImport(XlsxReader::read((string) $this->argument('file')));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Found in the old system:');
        $this->table(['What', 'How many'], collect($import->summary)->map(fn ($n, $k) => [$k, $n])->values()->all());

        if ($import->stockCheck) {
            $this->warn('These parts do not add up in the old system itself (opening + bought − sold ± adjusted ≠ current stock):');
            foreach ($import->stockCheck as $line) {
                $this->line('  '.$line);
            }
        } else {
            $this->info('✓ Every part adds up: opening stock + purchases − sales ± adjustments = current stock.');
        }
        if ($import->skippedPayments) {
            $this->warn('Payments that look like duplicates and will NOT be imported:');
            foreach ($import->skippedPayments as $s) {
                $this->line("  {$s['no']} ({$s['kind']}): {$s['why']}");
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

        if ($this->option('fresh')) {
            if (! $this->option('no-interaction') && $this->ask('--fresh deletes ALL parts, customers, job cards, sales and purchases now in the system, then imports. Type DELETE to continue') !== 'DELETE') {
                $this->warn('Nothing was changed.');

                return self::FAILURE;
            }
            $this->call('data:clear', ['--force' => true]);
        } elseif ($this->option('remove-demo')) {
            $this->call('demo:remove', ['--force' => true]);
        }
        try {
            $result = $import->commit();
        } catch (Throwable $e) {
            $this->error('Nothing was imported: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ([...$result['paid'], ...$result['stock']] as $line) {
            $this->warn('  ! '.$line);
        }
        $this->info($result['paid'] || $result['stock'] ? 'Imported, but check the lines above.' : '✓ Imported. Stock and payments match the old system.');

        return self::SUCCESS;
    }
}
