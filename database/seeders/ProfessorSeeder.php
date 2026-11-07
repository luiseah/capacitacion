<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Professor;
use Illuminate\Database\Seeder;

/**
 * Demo professors: each one is a new Spanish-named user with the requester role plus its
 * Professor profile (user_id foreign key). Skipped when there are already profiles, so it is
 * safe to run again. Use `php artisan db:seed --class=ProfessorSeeder`.
 */
class ProfessorSeeder extends Seeder
{
    private const COUNT = 12;

    public function run(): void
    {
        // Idempotent: makes sure the roles exist when run on its own.
        $this->call(RolesAndPermissionsSeeder::class);

        if (Professor::query()->exists()) {
            $this->command?->info('professors already seeded, skipping.');

            return;
        }

        Professor::factory()
            ->count(self::COUNT)
            ->create()
            ->each(fn (Professor $profile) => $profile->user->assignRole(RoleName::Requester->value));
    }
}
