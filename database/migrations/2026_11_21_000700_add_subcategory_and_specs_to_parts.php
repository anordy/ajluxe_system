<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website catalogue structure:
 *  - category    = department (Car Parts, Tyres, Filters, Engine Oil, Car Accessories)
 *  - subcategory = type inside it (Brake Pads, Air Filter, All-terrain…)
 *  - specs       = key/value list shown as a specification table (Size, Viscosity, Capacity…)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $t) {
            $t->string('subcategory', 80)->nullable()->after('category');
            $t->json('specs')->nullable()->after('web_description');
        });
    }

    public function down(): void
    {
        Schema::table('parts', fn (Blueprint $t) => $t->dropColumn(['subcategory', 'specs']));
    }
};
