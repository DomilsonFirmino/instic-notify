<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('informativo_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('informativo_id')->constrained('informativos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('decision', [
                'aprovado',    // aprovado
                'rejeitado',   // rejeitado definitivamente
                'revisao'      // precisa de correção
            ]);
            $table->text('comment');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['informativo_id', 'created_at']);
            $table->index(['reviewer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informativo_reviews');
    }
};
