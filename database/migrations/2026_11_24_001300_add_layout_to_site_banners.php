<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Banners can be "text" (title, text, button and picture) or "image" (a finished design, shown as it is). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_banners', fn (Blueprint $t) => $t->string('layout', 10)->default('text')->after('id'));
    }

    public function down(): void
    {
        Schema::table('site_banners', fn (Blueprint $t) => $t->dropColumn('layout'));
    }
};
