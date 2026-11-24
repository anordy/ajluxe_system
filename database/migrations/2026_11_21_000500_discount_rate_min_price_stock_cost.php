<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 *  - job_cards.discount_rate: discount is now a percentage of the subtotal
 *  - parts.min_selling_price: lowest price staff may sell at
 *  - job_card_items.stock_cost / invoice_items.stock_cost: what the part cost the business (buying price).
 *    On garage jobs "cost" is now what the garage pays the spare-parts shop (its selling price by default),
 *    so the difference is the spare-parts shop's profit on parts supplied to the garage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $t) {
            $t->decimal('discount_rate', 5, 2)->default(0)->after('subtotal');
        });
        Schema::table('parts', function (Blueprint $t) {
            $t->decimal('min_selling_price', 14, 2)->default(0)->after('selling_price');
        });
        Schema::table('job_card_items', function (Blueprint $t) {
            $t->decimal('stock_cost', 14, 2)->nullable()->after('cost');
        });
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->decimal('stock_cost', 14, 2)->nullable()->after('cost');
        });

        // existing data: keep today's numbers
        DB::table('job_cards')->where('subtotal', '>', 0)->where('discount', '>', 0)
            ->update(['discount_rate' => DB::raw('ROUND(discount * 100 / subtotal, 2)')]);
        DB::table('job_card_items')->whereNotNull('part_id')->update(['stock_cost' => DB::raw('cost')]);
        DB::table('invoice_items')->whereNotNull('part_id')->update(['stock_cost' => DB::raw('cost')]);
    }

    public function down(): void
    {
        Schema::table('job_cards', fn (Blueprint $t) => $t->dropColumn('discount_rate'));
        Schema::table('parts', fn (Blueprint $t) => $t->dropColumn('min_selling_price'));
        Schema::table('job_card_items', fn (Blueprint $t) => $t->dropColumn('stock_cost'));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('stock_cost'));
    }
};
