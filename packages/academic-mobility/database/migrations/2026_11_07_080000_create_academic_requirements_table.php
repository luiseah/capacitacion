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

        if (! $schema->hasTable($prefix.'requirements')) {
            // The catalog: what a mobility has to bring. A requirement applies to a mobility when every
            // criterion it names matches; a null criterion matches anything (direction, scope, participant kind).
            $schema->create($prefix.'requirements', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->string('direction', 16)->nullable();
                $table->string('scope', 16)->nullable();
                $table->string('participant_kind', 20)->nullable();
                $table->boolean('is_mandatory')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('position')->default(0);
                // {"es": {"name": "...", "description": "..."}}: the texts in other languages.
                $table->json('translations')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable($prefix.'mobility_requirements')) {
            // What each mobility had to bring when it was created: a copy, so editing the catalog later
            // does not rewrite requests that already exist.
            $schema->create($prefix.'mobility_requirements', function (Blueprint $table) use ($prefix) {
                $table->ulid('id')->primary();
                $table->foreignUlid('mobility_id')->constrained($prefix.'mobilities')->cascadeOnDelete();
                $table->foreignUlid('requirement_id')->nullable()->constrained($prefix.'requirements')->nullOnDelete();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('is_mandatory')->default(true);
                $table->unsignedSmallInteger('position')->default(0);
                $table->json('translations')->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('academic-mobility.table_prefix', 'academic_');

        Schema::connection($this->getConnection())->dropIfExists($prefix.'mobility_requirements');
        Schema::connection($this->getConnection())->dropIfExists($prefix.'requirements');
    }
};
