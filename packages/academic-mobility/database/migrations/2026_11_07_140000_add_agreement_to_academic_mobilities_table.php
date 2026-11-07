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

        if ($schema->hasColumn($prefix.'mobilities', 'agreement_id')) {
            return;
        }

        // The agreement the mobility is made under (optional): one of the institution on the other side.
        $schema->table($prefix.'mobilities', function (Blueprint $table) use ($prefix) {
            $table->foreignUlid('agreement_id')->nullable()->constrained($prefix.'agreements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('academic-mobility.table_prefix', 'academic_');

        Schema::connection($this->getConnection())->table($prefix.'mobilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agreement_id');
        });
    }
};
