<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('academic-mobility.connection');
    }

    public function up(): void
    {
        $prefix = config('academic-mobility.table_prefix', 'academic_');
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable($prefix.'agreements')) {
            return;
        }

        // The agreements (convenios) of a partner institution with the local one: an institution
        // can have as many as it needs, each one with its own validity and activities.
        $schema->create($prefix.'agreements', function (Blueprint $table) use ($prefix) {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained($prefix.'institutions')->restrictOnDelete();
            $table->string('name', 200);
            // The number or code the agreement has in the institutions' records.
            $table->string('reference', 60)->nullable();
            // Validity: from `starts_on` to `ends_on`, both included; no end date means indefinite.
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            // What it covers, any of: training, research, extension, administrative, other.
            $table->json('activities');
            // What "other" is, when it is one of the activities.
            $table->string('other_activity', 200)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institution_id', 'starts_on']);
            $table->index('ends_on');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(config('academic-mobility.table_prefix', 'academic_').'agreements');
    }
};
