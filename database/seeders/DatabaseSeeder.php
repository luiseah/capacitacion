<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database: `php artisan migrate --seed` leaves every table filled.
     * It can run again: every seeder skips what is already there.
     */
    public function run(): void
    {
        // What the application needs to work.
        $this->call([
            RolesAndPermissionsSeeder::class,
            RequestTypeSeeder::class,
            InstitutionSeeder::class,
            RequirementSeeder::class,
            AgreementSeeder::class,
        ]);

        User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test', 'lastname' => 'User', 'email' => 'test@example.com']);

        User::query()->where('email', 'test@example.com')->first()->assignRole(RoleName::Admin->value);

        // Demo data (people with a known password, requests, mobilities) never goes to production.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
