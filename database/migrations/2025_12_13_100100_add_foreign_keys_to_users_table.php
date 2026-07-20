<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('year_id')->references('id')->on('years')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['year_id']);
            $table->dropForeign(['department_id']);
        });
    }
};
