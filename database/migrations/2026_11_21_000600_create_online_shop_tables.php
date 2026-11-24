<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online shop:
 *  - parts: show on website, website discount %, website description
 *  - shop_customers: customer logins (separate from staff "users")
 *  - orders / order_items: online orders; confirming one creates a spare-parts sale (invoice)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $t) {
            $t->boolean('show_online')->default(true)->after('is_active');
            $t->decimal('discount_percent', 5, 2)->default(0)->after('min_selling_price');
            $t->text('web_description')->nullable()->after('vehicle_model');
        });

        Schema::create('shop_customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('phone', 20)->unique();
            $t->string('email', 120)->nullable()->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamp('last_login_at')->nullable();
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('shop_customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('pending')->index(); // pending, confirmed, ready, completed, cancelled
            $t->string('fulfilment', 20)->default('pickup');      // pickup | delivery
            $t->string('delivery_address')->nullable();
            $t->string('contact_phone', 20);
            $t->string('payment_method', 30);                     // lipa_namba | bank | on_pickup
            $t->string('payment_reference', 80)->nullable();
            $t->text('notes')->nullable();
            $t->decimal('subtotal', 14, 2)->default(0);           // before website discounts
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('cancel_reason')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('part_id')->constrained();
            $t->string('name');
            $t->decimal('qty', 12, 2);
            $t->decimal('list_price', 14, 2);       // normal selling price at the time
            $t->decimal('discount_percent', 5, 2)->default(0);
            $t->decimal('price', 14, 2);            // price charged per unit
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shop_customers');
        Schema::table('parts', fn (Blueprint $t) => $t->dropColumn(['show_online', 'discount_percent', 'web_description']));
    }
};
