<?php

namespace Database\Factories;

use App\Enums\RequestTypeKey;
use App\Models\RequestType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestType>
 */
class RequestTypeFactory extends Factory
{
    protected $model = RequestType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->randomElement(RequestTypeKey::cases());

        return [
            'key' => $key,
            'name' => $key->label(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function ofKey(RequestTypeKey $key): static
    {
        return $this->state(fn () => ['key' => $key, 'name' => $key->label()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
