<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public const PROGRAMS = [
        'Ingeniería de Sistemas',
        'Ingeniería Industrial',
        'Ingeniería Civil',
        'Derecho',
        'Medicina',
        'Enfermería',
        'Psicología',
        'Administración de Empresas',
        'Contaduría Pública',
        'Comunicación Social',
        'Arquitectura',
        'Odontología',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->spanish(),
            'code' => fake()->unique()->numerify('20########'),
            'program' => fake()->randomElement(self::PROGRAMS),
            'semester' => fake()->numberBetween(1, 10),
            // Colombian grading scale: 0.0 to 5.0.
            'gpa' => fake()->randomFloat(2, 2.8, 5.0),
            'status' => fake()->randomElement([
                ...array_fill(0, 8, StudentStatus::Active),
                StudentStatus::Inactive,
                StudentStatus::Suspended,
            ]),
        ];
    }

    public function graduated(): static
    {
        return $this->state(['status' => StudentStatus::Graduated, 'semester' => 10]);
    }
}
