<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Models\Professor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professor>
 */
class ProfessorFactory extends Factory
{
    private const FACULTIES = [
        'Facultad de Ingeniería',
        'Facultad de Derecho y Ciencias Políticas',
        'Facultad de Salud',
        'Facultad de Ciencias Económicas y Empresariales',
        'Facultad de Comunicación y Publicidad',
        'Facultad de Educación',
        'Facultad de Arquitectura',
    ];

    private const RANKS = ['Instructor', 'Profesor Asistente', 'Profesor Asociado', 'Profesor Titular'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->spanish(),
            'employee_code' => fake()->unique()->numerify('DOC-#####'),
            'faculty' => fake()->randomElement(self::FACULTIES),
            'academic_rank' => fake()->randomElement(self::RANKS),
            'contract_type' => fake()->randomElement(ContractType::cases()),
            'hired_at' => fake()->dateTimeBetween('-20 years', '-2 months'),
        ];
    }
}
