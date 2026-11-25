<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parts kept by a nearby shop: sold on the website as if in stock, bought only when sold.
 * invoice_items.sourced_qty = how much of the line was bought from that shop for this sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->boolean('sourced')->default(false)->after('show_online');
            $table->foreignId('source_supplier_id')->nullable()->after('sourced')->constrained('suppliers')->nullOnDelete();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('sourced_qty', 12, 2)->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn('sourced_qty'));
        Schema::table('parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_supplier_id');
            $table->dropColumn('sourced');
        });
    }
};
