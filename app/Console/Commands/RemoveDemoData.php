<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Models\Service;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Removes the demo stock added by DemoShopSeeder (SKU starting with DEMO-). Your own data is never touched. */
class RemoveDemoData extends Command
{
    protected $signature = 'demo:remove {--force : Do not ask for confirmation}';

    protected $description = 'Remove the demo parts (SKU DEMO-…) and demo garage prices';

    public function handle(): int
    {
        $parts = Part::withTrashed()->where('sku', 'like', 'DEMO-%')->get();
        $serviceIds = json_decode((string) setting('demo_service_ids', '[]'), true) ?: [];

        if ($parts->isEmpty() && ! $serviceIds) {
            $this->info('No demo data found.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm("Remove {$parts->count()} demo parts and reset ".count($serviceIds).' demo garage prices?', true)) {
            return self::FAILURE;
        }

        $deleted = $kept = 0;
        foreach ($parts as $p) {
            $used = DB::table('invoice_items')->where('part_id', $p->id)->exists()
                || DB::table('job_card_items')->where('part_id', $p->id)->exists()
                || DB::table('order_items')->where('part_id', $p->id)->exists()
                || DB::table('purchase_order_items')->where('part_id', $p->id)->exists();
            if ($used) {
                // sold or ordered while testing: keep the history, hide the part
                $p->forceFill(['is_active' => false, 'show_online' => false])->save();
                $kept++;

                continue;
            }
            DB::table('stock_movements')->where('part_id', $p->id)->delete();
            if ($p->image_path && str_starts_with($p->image_path, 'demo-parts/')) {
                Storage::disk('public')->delete($p->image_path);
            }
            $p->forceDelete();
            $deleted++;
        }

        if ($serviceIds) {
            Service::whereIn('id', $serviceIds)->update(['rate' => 0]);
            Settings::set(['demo_service_ids' => '[]']);
        }

        $this->info("Removed {$deleted} demo parts.".($kept ? " {$kept} were used in test sales/orders, so they were hidden instead." : ''));

        return self::SUCCESS;
    }
}
