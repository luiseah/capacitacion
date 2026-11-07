<?php

namespace Database\Factories;

use App\Models\AdministrativeStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministrativeStaff>
 */
class AdministrativeStaffFactory extends Factory
{
    private const DEPENDENCIES = [
        'Registro Académico',
        'Bienestar Universitario',
        'Tesorería',
        'Talento Humano',
        'Relaciones Internacionales',
        'Biblioteca',
        'Admisiones',
        'Tecnologías de la Información',
    ];

    private const POSITIONS = [
        'Auxiliar administrativo',
        'Secretaria',
        'Profesional universitario',
        'Coordinador',
        'Director',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->spanish(),
            'employee_code' => fake()->unique()->numerify('ADM-#####'),
            'dependency' => fake()->randomElement(self::DEPENDENCIES),
            'position' => fake()->randomElement(self::POSITIONS),
            'hired_at' => fake()->dateTimeBetween('-15 years', '-2 months'),
        ];
    }
}
