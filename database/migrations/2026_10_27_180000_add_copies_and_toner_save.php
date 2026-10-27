<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_pages', function (Blueprint $table) {
            $table->unsignedTinyInteger('copies')->default(1)->after('position');
        });

        Schema::create('print_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('toner_save')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_settings');

        Schema::table('print_pages', function (Blueprint $table) {
            $table->dropColumn('copies');
        });
    }
};
