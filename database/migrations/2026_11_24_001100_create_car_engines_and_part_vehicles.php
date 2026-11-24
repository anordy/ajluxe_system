<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle database: make & model (car_models) → engines (car_engines),
 * and which cars each part fits (part_vehicles). The website shows make and model only;
 * engines are for staff (fitting the right part, imports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_engines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $t->string('code', 40)->nullable();          // engine code, e.g. 1KD-FTV
            $t->decimal('litres', 3, 1)->nullable();     // 3.0
            $t->string('fuel', 12)->default('Petrol');   // Petrol | Diesel | Hybrid | Electric
            $t->unsignedSmallInteger('year_from')->nullable();
            $t->unsignedSmallInteger('year_to')->nullable();
            $t->timestamps();
            $t->unique(['car_model_id', 'code', 'litres', 'fuel']);
        });

        Schema::create('part_vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_id')->constrained()->cascadeOnDelete();
            $t->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $t->foreignId('car_engine_id')->nullable()->constrained()->cascadeOnDelete(); // empty = all engines
            $t->timestamps();
            $t->unique(['part_id', 'car_model_id', 'car_engine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_vehicles');
        Schema::dropIfExists('car_engines');
    }
};
