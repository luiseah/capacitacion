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

        if ($schema->hasColumn($prefix.'agreements', 'parties')) {
            return;
        }

        // Bilateral (the default: the agreements that already existed) or multilateral.
        $schema->table($prefix.'agreements', function (Blueprint $table) {
            $table->string('parties', 20)->default('bilateral')->after('type')->index();
        });

        // The other institutions of a multilateral agreement; `institution_id` stays the main one.
        $schema->create($prefix.'agreement_institution', function (Blueprint $table) use ($prefix) {
            $table->foreignUlid('agreement_id')->constrained($prefix.'agreements')->cascadeOnDelete();
            $table->foreignUlid('institution_id')->constrained($prefix.'institutions')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['agreement_id', 'institution_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('academic-mobility.table_prefix', 'academic_');

        Schema::connection($this->getConnection())->dropIfExists($prefix.'agreement_institution');
        Schema::connection($this->getConnection())->table($prefix.'agreements', function (Blueprint $table) {
            $table->dropColumn('parties');
        });
    }
};
