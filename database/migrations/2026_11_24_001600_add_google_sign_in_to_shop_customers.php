<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Website customers can sign in with Google; such accounts may have no password. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_customers', function (Blueprint $t) {
            $t->string('google_id', 64)->nullable()->unique()->after('email');
            $t->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shop_customers', fn (Blueprint $t) => $t->dropColumn('google_id'));
    }
};
