<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->string('sofizpay_transaction_id')->nullable()->after('telegram_message_id');
            $table->string('cib_order_number', 64)->nullable()->index()->after('sofizpay_transaction_id');
            $table->string('cib_order_id', 128)->nullable()->after('cib_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['sofizpay_transaction_id', 'cib_order_number', 'cib_order_id']);
        });
    }
};
