<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  - parts.variant_of: the same product in another size (e.g. 1L and 5L of a 4L engine oil) points to the main part
 *  - import_requests: "Import for you" custom orders (engines, gearboxes, parts from Japan, China, Dubai…)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $t) {
            $t->foreignId('variant_of')->nullable()->after('subcategory')->constrained('parts')->nullOnDelete();
        });

        Schema::create('import_requests', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 20)->nullable()->unique();
            $t->string('item_type', 40);
            $t->string('description', 1000);
            $t->string('make', 60)->nullable();
            $t->string('model', 80)->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->string('engine_code', 40)->nullable();
            $t->string('vin', 20)->nullable();
            $t->string('source_country', 60)->nullable();
            $t->string('condition', 30)->nullable();
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->decimal('budget', 14, 2)->nullable();
            $t->string('name', 120)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email', 150)->nullable();
            $t->foreignId('shop_customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 12)->default('new');
            $t->decimal('quote_amount', 14, 2)->nullable();   // full price delivered, quoted to the customer
            $t->decimal('deposit_paid', 14, 2)->nullable();
            $t->date('eta')->nullable();
            $t->string('customer_message', 500)->nullable();  // shown to the customer in their account
            $t->string('staff_note', 1000)->nullable();       // staff only
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_requests');
        Schema::table('parts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('variant_of');
        });
    }
};
