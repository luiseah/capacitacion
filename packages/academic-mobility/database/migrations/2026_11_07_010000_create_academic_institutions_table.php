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
        $name = config('academic-mobility.table_prefix', 'academic_').'institutions';
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable($name)) {
            return;
        }

        $schema->create($name, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            // ISO 3166-1 alpha-2; comparing it decides whether a mobility is national or international.
            $table->char('country', 2)->index();
            // The institution the application belongs to (at most one).
            $table->boolean('is_local')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // At most one local institution: enforced by the database where partial indexes exist.
        if (in_array($schema->getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $schema->getConnection()->statement("create unique index {$name}_single_local on {$name} (is_local) where is_local");
        }
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(
            config('academic-mobility.table_prefix', 'academic_').'institutions',
        );
    }
};
