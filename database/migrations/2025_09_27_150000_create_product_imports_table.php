<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};

