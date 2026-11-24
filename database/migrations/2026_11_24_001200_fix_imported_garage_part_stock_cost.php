<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garage lines imported from the old garage system that use shop stock were saved with a
 * buying cost of 0 (the stock had already been sold in the old parts system). That made the
 * spare-parts business show their full price as profit. Give them the garage's cost instead,
 * so the shop earns no second margin on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['job_card_items', 'invoice_items'] as $table) {
            DB::table($table)->whereNotNull('part_id')->where('stock_cost', 0)->where('cost', '>', 0)
                ->update(['stock_cost' => DB::raw('cost')]);
        }
    }

    public function down(): void
    {
    }
};
