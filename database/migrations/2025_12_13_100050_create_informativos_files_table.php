<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('informativos_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('informativo_id')->constrained('informativos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informativos_files');
    }
};
