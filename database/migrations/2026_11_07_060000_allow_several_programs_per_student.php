<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person can study two programs at once: each one is its own student profile
 * (own code, semester and GPA). What stays unique is one profile per program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'program']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'program']);
            $table->unique('user_id');
        });
    }
};
