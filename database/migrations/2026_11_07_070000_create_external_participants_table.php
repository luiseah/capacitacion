<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitors of incoming mobilities: students, professors and staff of another
 * institution. They are not users and never mix with the internal people of
 * `students`, `professors` and `administrative_staff`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_participants', function (Blueprint $table) {
            $table->id();
            // The institution they come from (a partner institution of the academic mobility package).
            $table->foreignUlid('institution_id')
                ->constrained(config('academic-mobility.table_prefix', 'academic_').'institutions')
                ->restrictOnDelete();
            $table->string('kind', 20)->index();
            $table->string('name');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('document_number', 30);
            // Their code at their own institution.
            $table->string('code', 30)->nullable();
            // Program (students), faculty or department (professors), dependency (staff).
            $table->string('affiliation', 150);
            $table->timestamps();

            $table->unique(['institution_id', 'kind', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_participants');
    }
};
