<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\AdministrativeStaff;
use Illuminate\Database\Seeder;

/**
 * Demo administrative staff: each one is a new Spanish-named user with the requester role plus its
 * AdministrativeStaff profile (user_id foreign key). Skipped when there are already profiles, so it is
 * safe to run again. Use `php artisan db:seed --class=AdministrativeStaffSeeder`.
 */
class AdministrativeStaffSeeder extends Seeder
{
    private const COUNT = 8;

    public function run(): void
    {
        // Idempotent: makes sure the roles exist when run on its own.
        $this->call(RolesAndPermissionsSeeder::class);

        if (AdministrativeStaff::query()->exists()) {
            $this->command?->info('administrative staff already seeded, skipping.');

            return;
        }

        AdministrativeStaff::factory()
            ->count(self::COUNT)
            ->create()
            ->each(fn (AdministrativeStaff $profile) => $profile->user->assignRole(RoleName::Requester->value));
    }
}
