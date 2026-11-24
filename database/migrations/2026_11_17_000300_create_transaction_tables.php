<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactions for both sides of the business.
 *  - Garage: job cards -> items -> status history -> invoice (type "garage")
 *  - Parts shop: counter sales are invoices of type "counter"
 *  - Purchasing: purchase orders -> receiving -> supplier payments
 *  - Every stock change is written to stock_movements (one inventory for the business).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();               // JC-2026-0001
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('mechanic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('Open')->index();
            $table->string('priority', 20)->default('Normal');
            $table->unsignedInteger('odometer_in')->nullable();
            $table->string('fuel_level', 10)->nullable();
            $table->text('complaints')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->text('work_notes')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('promised_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                           // service | part | other
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('cost', 14, 2)->default(0);           // what the business paid (never printed)
            $table->timestamps();
        });

        Schema::create('job_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Garage invoices AND parts-shop counter sales live here.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();               // INV-2026-0001
            $table->string('type', 10)->index();                  // garage | counter
            $table->foreignId('job_card_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('walk_in_name')->nullable();           // counter sale with no saved customer
            $table->date('date')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status', 15)->default('unpaid')->index(); // unpaid | partial | paid | cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('cost', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();               // RCT-2026-0001
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete(); // advance before invoice
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('mode', 40);                           // Cash, M-Pesa, Bank Transfer ...
            $table->string('reference', 80)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();               // PO-2026-0001
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->date('expected_date')->nullable();
            $table->string('status', 15)->default('draft')->index(); // draft | ordered | received | cancelled
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 2);
            $table->decimal('received_qty', 12, 2)->default(0);
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();               // SPY-2026-0001
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('mode', 40);
            $table->string('reference', 80)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();               // CRN-2026-0001 / DBN-2026-0001
            $table->string('type', 10);                           // customer (credit note) | supplier (debit note)
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 2);
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->dateTime('date')->index();
            // opening | purchase | counter_sale | job | return_in | return_out | adjustment
            $table->string('type', 20)->index();
            $table->decimal('qty_in', 12, 2)->default(0);
            $table->decimal('qty_out', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->nullableMorphs('reference');                  // JobCard, Invoice, PurchaseOrder, ...
            $table->string('reference_no', 30)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_movements', 'return_items', 'returns', 'supplier_payments', 'purchase_order_items',
                  'purchase_orders', 'payments', 'invoice_items', 'invoices', 'job_status_histories',
                  'job_card_items', 'job_cards'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
