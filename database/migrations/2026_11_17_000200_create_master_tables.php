<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data shared by the Garage and the Parts Shop.
 * One customer list, one vehicle list, one parts inventory for the whole business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                 // CUS-0001
            $table->string('name');
            $table->string('type', 30)->default('Individual');    // Individual, Company, Fleet, Workshop
            $table->string('phone', 30)->nullable()->index();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tin', 40)->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('reg_no', 30)->unique();               // T 123 ABC
            $table->string('make', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->string('color', 30)->nullable();
            $table->string('vin', 40)->nullable();
            $table->string('engine_no', 40)->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                 // SUP-0001
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tin', 40)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name');
            $table->string('oem_no', 60)->nullable()->index();
            $table->string('brand', 60)->nullable();
            $table->string('category', 60)->nullable()->index();
            $table->string('unit', 20)->default('Pcs');
            $table->string('vehicle_make', 60)->nullable();
            $table->string('vehicle_model', 120)->nullable();
            $table->string('rack_location', 40)->nullable();
            $table->decimal('purchase_price', 14, 2)->default(0);  // last cost
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('stock_qty', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                 // SRV-001
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->string('pricing', 10)->default('fixed');      // fixed | hourly
            $table->decimal('rate', 14, 2)->default(0);           // price per job, or per hour
            $table->decimal('est_hours', 6, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('mechanics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('specialization', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanics');
        Schema::dropIfExists('services');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('customers');
    }
};
