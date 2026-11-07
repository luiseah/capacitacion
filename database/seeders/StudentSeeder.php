<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Student;
use Database\Factories\StudentFactory;
use Illuminate\Database\Seeder;

/**
 * Demo students: each one is a new Spanish-named user with the requester role plus its
 * Student profile (user_id foreign key); a few also study a second program (another profile of the same user). Skipped when there are already profiles, so it is
 * safe to run again. Use `php artisan db:seed --class=StudentSeeder`.
 */
class StudentSeeder extends Seeder
{
    private const COUNT = 30;

    /** Students that also study a second program. */
    private const DOUBLE_PROGRAM = 5;

    public function run(): void
    {
        // Idempotent: makes sure the roles exist when run on its own.
        $this->call(RolesAndPermissionsSeeder::class);

        if (Student::query()->exists()) {
            $this->command?->info('students already seeded, skipping.');

            return;
        }

        $students = Student::factory()
            ->count(self::COUNT)
            ->create()
            ->each(fn (Student $profile) => $profile->user->assignRole(RoleName::Requester->value));

        // Some people study two programs at once: same user, another student profile.
        $students->take(self::DOUBLE_PROGRAM)->each(fn (Student $profile) => Student::factory()
            ->for($profile->user)
            ->create(['program' => collect(StudentFactory::PROGRAMS)->reject($profile->program)->random()]));
    }
}
