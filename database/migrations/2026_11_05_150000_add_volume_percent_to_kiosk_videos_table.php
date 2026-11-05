<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_videos', function (Blueprint $table) {
            $table->unsignedTinyInteger('volume_percent')->default(100)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_videos', function (Blueprint $table) {
            $table->dropColumn('volume_percent');
        });
    }
};
