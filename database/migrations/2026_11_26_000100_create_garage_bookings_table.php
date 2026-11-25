<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Book your car in" requests from the website's garage page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garage_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('car', 120);
            $table->string('plate', 30)->nullable();
            $table->text('work');
            $table->date('preferred_date')->nullable();
            $table->string('status', 20)->default('new')->index();   // new, confirmed, booked_in, cancelled
            $table->foreignId('shop_customer_id')->nullable()->constrained('shop_customers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained('job_cards')->nullOnDelete();
            $table->string('staff_note', 500)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garage_bookings');
    }
};
