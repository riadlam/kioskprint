<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('paid_at');
        });

        Schema::table('print_settings', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('toner_save');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn('telegram_message_id');
        });

        Schema::table('print_settings', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
