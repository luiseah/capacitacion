<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The activities of an agreement are training, research, extension, administrative and other.
 * Agreements saved with the first list keep their meaning: mobility is training, management is administrative.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('academic-mobility.connection');
    }

    public function up(): void
    {
        $this->rename(['mobility' => 'training', 'management' => 'administrative']);
    }

    public function down(): void
    {
        $this->rename(['training' => 'mobility', 'administrative' => 'management', 'extension' => 'other']);
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rename(array $map): void
    {
        $table = config('academic-mobility.table_prefix', 'academic_').'agreements';

        DB::connection($this->getConnection())->table($table)->orderBy('id')->each(function (object $agreement) use ($map, $table) {
            $activities = array_values(array_unique(array_map(
                fn (string $activity) => $map[$activity] ?? $activity,
                (array) json_decode($agreement->activities, true),
            )));

            DB::connection($this->getConnection())->table($table)->where('id', $agreement->id)->update(['activities' => json_encode($activities)]);
        });
    }
};
