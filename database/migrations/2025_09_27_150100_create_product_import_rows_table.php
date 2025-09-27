<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')->constrained('product_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('sku')->nullable();
            $table->string('status'); // imported, updated, invalid, duplicate
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
    }
};

