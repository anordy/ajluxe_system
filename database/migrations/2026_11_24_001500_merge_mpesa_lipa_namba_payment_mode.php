<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** "M-Pesa (Lipa Namba)" and "M-Pesa" are one payment method: M-Pesa. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['payments', 'supplier_payments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'mode')) {
                DB::table($table)->where('mode', 'like', 'M-Pesa%Lipa%')->update(['mode' => 'M-Pesa']);
            }
        }
        DB::table('list_options')->where('list', 'payment_mode')->where('value', 'like', 'M-Pesa%Lipa%')->delete();
    }

    public function down(): void
    {
    }
};
