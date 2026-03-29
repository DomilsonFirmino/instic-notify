<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Drop the composite primary key
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropPrimary(['user_id', 'informativo_id']);
        });

        // 2. Add auto-increment primary key
        Schema::table('favorites', function (Blueprint $table) {
            $table->id()->first();
        });

        // 3. Add unique constraint to prevent duplicates
        Schema::table('favorites', function (Blueprint $table) {
            $table->unique(['user_id', 'informativo_id']);
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'informativo_id']);
            $table->dropColumn('id');
            $table->primary(['user_id', 'informativo_id']);
        });
    }
};
