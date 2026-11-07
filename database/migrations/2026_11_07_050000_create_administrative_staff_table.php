<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrative staff of the university: one profile per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code', 20)->unique();
            $table->string('dependency', 120);
            $table->string('position', 120);
            $table->date('hired_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_staff');
    }
};
