<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->uuid('print_code_id')->nullable()->after('kiosk_id');
            $table->string('title')->nullable()->after('print_code_id');
            $table->foreign('print_code_id')->references('id')->on('print_codes')->nullOnDelete();
            $table->index('print_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['print_code_id']);
            $table->dropColumn(['print_code_id', 'title']);
        });
    }
};
