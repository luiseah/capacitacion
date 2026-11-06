<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Each request type maps to one strategy. `key` stores the value of the
        // PHP backed enum whose cases resolve the class that validates,
        // processes and transitions requests of that type.
        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('request_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            // Values for the fields declared by the request model, keyed by field key.
            $table->jsonb('metadata')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['request_type_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });

        // GIN indexes are PostgreSQL-only (tests run on in-memory SQLite).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX requests_metadata_gin_index ON requests USING GIN (metadata jsonb_path_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
        Schema::dropIfExists('request_types');
    }
};
