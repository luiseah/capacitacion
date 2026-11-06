<?php

namespace Database\Factories;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RequestTypeKey;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_type_id' => RequestType::factory(),
            'requester_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'status' => RequestStatus::Draft,
            'priority' => RequestPriority::Normal,
            'metadata' => [],
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => RequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Use the existing request type of the given key (creating it if needed).
     */
    public function ofType(RequestTypeKey $key): static
    {
        return $this->state(fn () => [
            'request_type_id' => RequestType::firstOrCreate(
                ['key' => $key->value],
                ['name' => $key->label()],
            )->id,
        ]);
    }

    public function forRequester(User $user): static
    {
        return $this->state(fn () => ['requester_id' => $user->id]);
    }
}
