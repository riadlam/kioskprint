<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_pcs', function (Blueprint $table) {
            $table->json('health')->nullable()->after('connected_since');
            $table->timestamp('health_reported_at')->nullable()->after('health');
        });

        Schema::table('print_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('status_message_id')->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_pcs', function (Blueprint $table) {
            $table->dropColumn(['health', 'health_reported_at']);
        });

        Schema::table('print_settings', function (Blueprint $table) {
            $table->dropColumn('status_message_id');
        });
    }
};
