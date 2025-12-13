<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('informativo_id')->constrained('informativos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'informativo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
