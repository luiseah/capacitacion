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

        if ($schema->hasColumn($prefix.'mobilities', 'direction')) {
            return;
        }

        $schema->table($prefix.'mobilities', function (Blueprint $table) use ($prefix) {
            // Derived when the mobility is created, stored so it can be filtered.
            $table->string('direction', 16)->nullable()->index();
            $table->string('scope', 16)->nullable()->index();

            $table->foreignUlid('origin_institution_id')->nullable()->constrained($prefix.'institutions')->restrictOnDelete();
            $table->foreignUlid('destination_institution_id')->nullable()->constrained($prefix.'institutions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('academic-mobility.table_prefix', 'academic_');

        Schema::connection($this->getConnection())->table($prefix.'mobilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_institution_id');
            $table->dropConstrainedForeignId('destination_institution_id');
            $table->dropColumn(['direction', 'scope']);
        });
    }
};
