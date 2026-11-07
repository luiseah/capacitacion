<?php

namespace Database\Seeders;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RequestTypeKey;
use App\Models\ServiceRequest;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * Demo service requests (library, purchases, grade changes, work permits) of the demo students,
 * in every state. Skipped when there already are requests, so it is safe to run again.
 * Use `php artisan db:seed --class=ServiceRequestSeeder`.
 */
class ServiceRequestSeeder extends Seeder
{
    private const PER_TYPE = 6;

    public function run(): void
    {
        $this->call([RequestTypeSeeder::class, StudentSeeder::class]);

        if (ServiceRequest::query()->withoutGlobalScopes()->exists()) {
            $this->command?->info('requests already seeded, skipping.');

            return;
        }

        $requesters = Student::query()->with('user')->orderBy('id')->get()->pluck('user')->unique('id')->values();
        $statuses = RequestStatus::cases();
        $priorities = RequestPriority::cases();
        $n = 0;

        foreach (RequestTypeKey::cases() as $key) {
            foreach (range(1, self::PER_TYPE) as $i) {
                $status = $statuses[$n % count($statuses)];

                ServiceRequest::factory()
                    ->ofType($key)
                    ->forRequester($requesters[$n % $requesters->count()])
                    ->create([
                        'status' => $status,
                        'priority' => $priorities[$n % count($priorities)],
                        'submitted_at' => $status === RequestStatus::Draft ? null : now()->subDays($n % 20),
                    ]);

                $n++;
            }
        }
    }
}
