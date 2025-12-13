<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('informativos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('status', [
                'rascunho',      // Em edição pelo autor
                'pendente',      // Enviado para revisão
                'revisao',       // Precisa de correção
                'aprovado',      // Aprovado, aguardando publicação
                'agendado',      // Publicação programada
                'publicado',     // Já está visível
                'despublicado',  // Foi retirado do ar
                'rejeitado'      // Recusado definitivamente
            ]);
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('year_id')->nullable()->constrained('years')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informativos');
    }
};
