<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_screen_settings', function (Blueprint $table) {
            $table->boolean('muted')->default(false)->after('idle_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_screen_settings', function (Blueprint $table) {
            $table->dropColumn('muted');
        });
    }
};
