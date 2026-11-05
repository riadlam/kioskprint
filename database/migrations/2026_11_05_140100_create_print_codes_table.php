<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('pin', 4)->nullable()->unique();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedInteger('page_count')->default(0);
            $table->decimal('unit_price', 8, 2)->default(20);
            $table->string('currency', 8)->default('DA');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_codes');
    }
};
