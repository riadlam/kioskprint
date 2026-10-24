<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_pcs', function (Blueprint $table) {
            $table->id();
            $table->string('kiosk_id', 64)->unique();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kiosk_print_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('print_job_id')->constrained('print_jobs')->cascadeOnDelete();
            $table->string('kiosk_id', 64);
            $table->unsignedInteger('position');
            $table->json('page_ids');
            $table->string('path');
            $table->boolean('duplex')->default(false);
            $table->string('state', 20)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['kiosk_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_print_units');
        Schema::dropIfExists('kiosk_pcs');
    }
};
