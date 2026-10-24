<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->unsignedInteger('page_count')->default(0)->after('status');
            $table->decimal('amount', 10, 2)->default(0)->after('page_count');
            $table->string('currency', 8)->default('DA')->after('amount');
            $table->string('payment_method', 32)->nullable()->after('currency');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['page_count', 'amount', 'currency', 'payment_method', 'paid_at']);
        });
    }
};
