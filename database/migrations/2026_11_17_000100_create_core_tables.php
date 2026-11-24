<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core / shared tables: settings, dropdown lists, document numbering, audit log, attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('list_options', function (Blueprint $table) {
            $table->id();
            $table->string('list', 60)->index();          // e.g. "vehicle_make", "payment_mode"
            $table->string('value', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['list', 'value']);
        });

        // Document numbers (JC-2026-0001, INV-2026-0001 ...) issued inside a locked transaction.
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('key', 40);                    // e.g. "job_card"
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['key', 'year']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module', 60);
            $table->string('action', 40);
            $table->string('record', 80)->nullable();
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('list_options');
        Schema::dropIfExists('settings');
    }
};
