<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: returns.
 *  - invoices.credited / purchase_orders.credited: value of goods returned (credit / debit notes)
 *    balance = grand_total − credited − paid
 *  - returns.refund_amount / refund_mode: money given back (to the customer, or by the supplier)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('credited', 14, 2)->default(0)->after('grand_total');
            $table->string('void_reason')->nullable()->after('notes');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('credited', 14, 2)->default(0)->after('grand_total');
        });
        Schema::table('returns', function (Blueprint $table) {
            $table->decimal('refund_amount', 14, 2)->default(0)->after('total');
            $table->string('refund_mode', 40)->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['credited', 'void_reason']));
        Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropColumn('credited'));
        Schema::table('returns', fn (Blueprint $t) => $t->dropColumn(['refund_amount', 'refund_mode']));
    }
};
