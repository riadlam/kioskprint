<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('print_job_id')->nullable()->constrained('print_jobs')->nullOnDelete();
            $table->string('kiosk_id', 64);
            $table->unsignedTinyInteger('pulses');
            $table->unsignedSmallInteger('dinars');
            $table->string('event_key', 80)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_events');
    }
};
