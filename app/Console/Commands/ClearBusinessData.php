<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\OldSystemImport;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes ALL parts, stock, customers, vehicles, job cards, invoices, payments, suppliers, purchase orders, returns,
 * online orders and website requests — demo and test data included — so the real data can be imported on a clean system.
 * Keeps: staff users, settings, lists, garage services (prices), mechanics, car list, banners, website texts, audit log.
 * A backup of everything deleted is written to storage/app/backups first.
 */
class ClearBusinessData extends Command
{
    protected $signature = 'data:clear {--force : Do not ask (used by import:old-system --fresh)}';

    protected $description = 'Delete all demo and test business data (parts, customers, job cards, sales, purchases …) before importing real data';

    /** children before parents */
    public const TABLES = [
        'part_vehicles', 'order_items', 'orders', 'return_items', 'returns', 'payments', 'supplier_payments',
        'invoice_items', 'invoices', 'job_status_histories', 'job_card_items', 'job_cards', 'purchase_order_items', 'purchase_orders',
        'stock_movements', 'attachments', 'vehicles', 'customers', 'suppliers', 'parts', 'shop_customers', 'car_requests', 'import_requests',
    ];

    public function handle(): int
    {
        $counts = collect(self::TABLES)->filter(fn ($t) => Schema::hasTable($t))->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $this->table(['Table', 'Rows to delete'], $counts->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line('Kept: staff users, settings, lists, garage services, mechanics, car list, banners, website texts, audit log.');

        if (! $this->option('force') && $this->ask('This cannot be undone (a backup file is made first). Type DELETE to continue') !== 'DELETE') {
            $this->warn('Nothing was deleted.');

            return self::FAILURE;
        }

        // 1. backup
        $backup = [];
        foreach ($counts->keys() as $t) {
            $backup[$t] = DB::table($t)->get();
        }
        $path = 'backups/before-clear-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode($backup, JSON_PRETTY_PRINT));
        $this->info('Backup written: storage/app/'.$path);

        // 2. part pictures
        foreach (DB::table('parts')->whereNotNull('image_path')->pluck('image_path') as $img) {
            Storage::disk('public')->delete($img);
        }
        Storage::disk('public')->deleteDirectory('demo-parts');

        // 3. delete, children first; numbering starts again at 0001
        Schema::disableForeignKeyConstraints();
        foreach ($counts->keys() as $t) {
            DB::table($t)->delete();
        }
        // numbering of the deleted documents starts again; services are kept, so their code counter is kept too
        DB::table('sequences')->whereIn('key', ['customer', 'part', 'supplier', 'invoice', 'job_card', 'receipt', 'po', 'supplier_payment', 'credit_note', 'debit_note', 'order'])->delete();
        Schema::enableForeignKeyConstraints();

        // 4. demo garage prices back to "ask us", and forget earlier imports
        $demo = json_decode((string) Settings::get('demo_service_ids', '[]'), true) ?: [];
        if ($demo) {
            Service::whereIn('id', $demo)->update(['rate' => 0]);
        }
        Settings::set(['demo_service_ids' => '[]', OldSystemImport::STATE_KEY => '{}', \App\Services\OldGarageImport::STATE_KEY => '{}']);

        Audit::log('System', 'CLEAR_DATA', 'All business data', $counts->sum().' rows deleted, backup '.$path);
        $this->info('Done: '.$counts->sum().' rows deleted.');

        return self::SUCCESS;
    }
}
