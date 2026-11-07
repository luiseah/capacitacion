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
        $name = config('academic-mobility.table_prefix', 'academic_').'agreements';
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasColumn($name, 'status')) {
            return;
        }

        $schema->table($name, function (Blueprint $table) {
            // Draft, in process, in force, expired, suspended or terminated.
            $table->string('status', 20)->default('active')->after('parties')->index();
            // What the agreement is for, formally.
            $table->text('purpose')->nullable()->after('reference');
            // When it was signed: not necessarily when it starts to be in force.
            $table->date('signed_on')->nullable()->after('ends_on');
            // Automatic, manual or not renewable.
            $table->string('renewal', 20)->default('manual')->after('signed_on');
            // The signed document (PDF) and the name it was uploaded with.
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
        });

        // Agreements that already existed were in force, expired or still to start: say so.
        $connection = $schema->getConnection();
        $today = now()->toDateString();

        $connection->table($name)->whereNotNull('ends_on')->whereDate('ends_on', '<', $today)->update(['status' => 'expired']);
        $connection->table($name)->whereDate('starts_on', '>', $today)->update(['status' => 'in_process']);
    }

    public function down(): void
    {
        $name = config('academic-mobility.table_prefix', 'academic_').'agreements';

        Schema::connection($this->getConnection())->table($name, function (Blueprint $table) {
            $table->dropColumn(['status', 'purpose', 'signed_on', 'renewal', 'document_path', 'document_name']);
        });
    }
};
