<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Drop foreign keys that depend on the composite primary key index
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['informativo_id']);
        });

        // 2. Drop the composite primary key
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropPrimary(['user_id', 'informativo_id']);
        });

        // 3. Add auto-increment primary key
        Schema::table('favorites', function (Blueprint $table) {
            $table->id()->first();
        });

        // 4. Add unique constraint to prevent duplicates and restore foreign keys
        Schema::table('favorites', function (Blueprint $table) {
            $table->unique(['user_id', 'informativo_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('informativo_id')->references('id')->on('informativos')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['informativo_id']);
            $table->dropUnique(['user_id', 'informativo_id']);
            $table->dropColumn('id');
            $table->primary(['user_id', 'informativo_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('informativo_id')->references('id')->on('informativos')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }
};
