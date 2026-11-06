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
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            RequestTypeSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assignRole(RoleName::SuperAdmin->value);
    }
}
