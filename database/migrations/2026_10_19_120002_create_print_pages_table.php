<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_document_id')->constrained('print_documents')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->timestamps();

            $table->unique(['print_document_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_pages');
    }
};
