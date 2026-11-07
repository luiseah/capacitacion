<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Shared Spanish faker, so `unique()` holds across every generated user.
     */
    protected static ?Generator $spanishFaker = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Spanish first name, two surnames and a phone, with an ASCII email built from the first name,
     * last name and a number, e.g. `jose.nunez.482@example.com`.
     */
    public function spanish(): static
    {
        return $this->state(function () {
            $faker = static::$spanishFaker ??= FakerFactory::create('es_ES');

            $first = $faker->firstName();
            $last = $faker->lastName();
            $handle = Str::slug("{$first}.{$last}", '.');

            return [
                'name' => $first,
                'lastname' => "{$last} {$faker->lastName()}",
                'phone' => '+34 '.$faker->numerify('6## ### ###'),
                'email' => $faker->unique()->numerify("{$handle}.###")."@example.com",
            ];
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
