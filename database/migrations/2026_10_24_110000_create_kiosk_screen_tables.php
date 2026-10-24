<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_screen_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('idle_seconds')->default(60);
            $table->timestamps();
        });

        Schema::create('kiosk_videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('position');
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_videos');
        Schema::dropIfExists('kiosk_screen_settings');
    }
};
