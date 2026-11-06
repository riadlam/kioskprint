<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_price_bands', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_pages');
            $table->unsignedInteger('max_pages');
            $table->decimal('price_per_page', 8, 2);
            $table->timestamps();
        });

        $now = now();
        DB::table('print_price_bands')->insert([
            ['min_pages' => 1, 'max_pages' => 9, 'price_per_page' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['min_pages' => 10, 'max_pages' => 39, 'price_per_page' => 15, 'created_at' => $now, 'updated_at' => $now],
            ['min_pages' => 40, 'max_pages' => 100, 'price_per_page' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('print_price_bands');
    }
};
