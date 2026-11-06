<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sheets_left')->default(0);
            $table->unsignedInteger('filled_sheets')->default(0);
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('low_alerted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('paper_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('print_job_id')->nullable()->constrained('print_jobs')->nullOnDelete();
            $table->unsignedInteger('sheets');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_uses');
        Schema::dropIfExists('paper_stocks');
    }
};
