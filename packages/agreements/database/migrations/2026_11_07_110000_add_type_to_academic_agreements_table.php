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
        $table = config('academic-mobility.table_prefix', 'academic_').'agreements';
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasColumn($table, 'type')) {
            return;
        }

        $schema->table($table, function (Blueprint $table) {
            // Framework (marco) or specific. Agreements that already existed are taken as specific.
            $table->string('type', 20)->default('specific')->after('institution_id')->index();
            // The framework agreement a specific one develops (optional).
            $table->foreignUlid('framework_id')->nullable()->after('type')->constrained($table->getTable())->nullOnDelete();
        });
    }

    public function down(): void
    {
        $table = config('academic-mobility.table_prefix', 'academic_').'agreements';

        Schema::connection($this->getConnection())->table($table, function (Blueprint $table) {
            $table->dropConstrainedForeignId('framework_id');
            $table->dropColumn('type');
        });
    }
};
