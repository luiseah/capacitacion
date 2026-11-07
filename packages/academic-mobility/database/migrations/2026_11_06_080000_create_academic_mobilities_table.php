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
        $table = config('academic-mobility.table_prefix', 'academic_').'mobilities';

        // Safe when the migration was published and the package also loads it.
        if (Schema::connection($this->getConnection())->hasTable($table)) {
            return;
        }

        Schema::connection($this->getConnection())->create($table, function (Blueprint $table) {
            $table->ulid('id')->primary();

            // The participant is referenced, never joined: `type` is an alias
            // resolved by the host (student, professor...) and `id` is a string
            // so any key type fits.
            $table->string('participant_type', 64);
            $table->string('participant_id', 64);

            $table->string('strategy', 64);
            $table->string('status', 32)->index();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['participant_type', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(
            config('academic-mobility.table_prefix', 'academic_').'mobilities',
        );
    }
};
