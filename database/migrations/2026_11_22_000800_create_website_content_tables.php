<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website content managed from the admin portal:
 *  - site_banners: the scrolling ads on the home page
 *  - car_models:   cars in the "find parts for your car" list
 *  - car_requests: "Can't find your car?" requests sent from the website
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_banners', function (Blueprint $t) {
            $t->id();
            $t->string('kicker', 60)->nullable();        // small line above the title
            $t->string('title', 120);
            $t->string('text', 300)->nullable();
            $t->string('button_label', 40)->nullable();
            $t->string('link_url', 300)->nullable();
            $t->string('image_path')->nullable();
            $t->string('tone', 10)->default('ink');      // red | ink | grey
            $t->foreignId('part_id')->nullable()->constrained()->nullOnDelete(); // promote one part (price, picture, link)
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->timestamps();
        });

        Schema::create('car_models', function (Blueprint $t) {
            $t->id();
            $t->string('make', 60);
            $t->string('model', 80);
            $t->unsignedSmallInteger('year_from')->nullable();
            $t->unsignedSmallInteger('year_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['make', 'model']);
        });

        Schema::create('car_requests', function (Blueprint $t) {
            $t->id();
            $t->string('vin', 20)->nullable();
            $t->string('make', 60);
            $t->string('model', 80);
            $t->unsignedSmallInteger('year')->nullable();
            $t->string('parts', 500)->nullable();
            $t->string('name', 120)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email', 150)->nullable();
            $t->boolean('wants_offers')->default(false);
            $t->foreignId('shop_customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 12)->default('new');    // new | contacted | added | closed
            $t->string('staff_note', 500)->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_requests');
        Schema::dropIfExists('car_models');
        Schema::dropIfExists('site_banners');
    }
};
