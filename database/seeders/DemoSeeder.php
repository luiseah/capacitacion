<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything that is made up for demos and development: random users, the university's own people,
 * the visitors of other institutions, service requests and academic mobilities. Every seeder takes
 * care of what it needs, so any of them can also run on its own.
 * Use `php artisan db:seed --class=DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            StudentSeeder::class,
            ProfessorSeeder::class,
            AdministrativeStaffSeeder::class,
            ExternalParticipantSeeder::class,
            ServiceRequestSeeder::class,
            MobilitySeeder::class,
        ]);
    }
}
