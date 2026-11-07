<?php

namespace Database\Seeders;

use App\Models\ExternalParticipant;
use Illuminate\Database\Seeder;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Demo visitors of incoming mobilities: a few students, professors and staff for every
 * partner institution (all the foreign ones and some Colombian ones). They live in their own
 * table, apart from the university's people. Skipped when there already are visitors.
 * Use `php artisan db:seed --class=ExternalParticipantSeeder`.
 */
class ExternalParticipantSeeder extends Seeder
{
    /** Visitors per institution: kind => how many. */
    private const PER_INSTITUTION = ['student' => 2, 'professor' => 1, 'staff' => 1];

    /** Colombian partner institutions that also send visitors. */
    private const NATIONAL = 5;

    public function run(): void
    {
        // The institutions they come from.
        $this->call(InstitutionSeeder::class);

        if (ExternalParticipant::query()->exists()) {
            $this->command?->info('external participants already seeded, skipping.');

            return;
        }

        $local = Institution::local();
        $partners = Institution::query()->where('is_local', false);

        $institutions = (clone $partners)->where('country', '!=', $local?->country)->orderBy('name')->get()
            ->merge((clone $partners)->where('country', $local?->country)->orderBy('name')->limit(self::NATIONAL)->get());

        foreach ($institutions as $institution) {
            foreach (self::PER_INSTITUTION as $kind => $count) {
                ExternalParticipant::factory()
                    ->count($count)
                    ->from($institution)
                    ->ofKind(ParticipantKind::from($kind))
                    ->create();
            }
        }
    }
}
