<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Random users with Spanish names. Skipped when there already are users besides the test account,
 * so it is safe to run again. Use `php artisan db:seed --class=UserSeeder`.
 */
class UserSeeder extends Seeder
{
    private const COUNT = 50;

    public function run(): void
    {
        // Idempotent: makes sure the roles exist when run on its own.
        $this->call(RolesAndPermissionsSeeder::class);

        // The database already has people beyond the test account: do not add more every time.
        if (User::query()->where('email', '!=', 'test@example.com')->exists()) {
            $this->command?->info('users already seeded, skipping.');

            return;
        }

        User::factory()
            ->spanish()
            ->count(self::COUNT)
            ->sequence(fn ($sequence) => ['email_verified_at' => $sequence->index % 10 === 0 ? null : now()])
            ->create()
            ->each(fn (User $user, int $i) => $user->assignRole(
                $i % 10 === 1 ? RoleName::Admin->value : RoleName::Requester->value,
            ));
    }
}
