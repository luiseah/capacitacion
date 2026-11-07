<?php

namespace Database\Factories;

use App\Models\ExternalParticipant;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Visitors of another institution; their names follow the language of its country.
 *
 * @extends Factory<ExternalParticipant>
 */
class ExternalParticipantFactory extends Factory
{
    private const LOCALES = [
        'CO' => 'es_ES', 'ES' => 'es_ES', 'MX' => 'es_MX', 'AR' => 'es_AR', 'CL' => 'es_CL',
        'BR' => 'pt_BR', 'FR' => 'fr_FR', 'DE' => 'de_DE', 'CA' => 'en_CA', 'US' => 'en_US',
    ];

    private const PROGRAMS = [
        'Computer Science', 'Law', 'Medicine', 'Economics', 'Architecture', 'Biology',
        'Psychology', 'Mechanical Engineering', 'Business Administration', 'Communication',
    ];

    private const DEPARTMENTS = [
        'Department of Computer Science', 'Faculty of Law', 'Faculty of Medicine', 'Department of Economics',
        'School of Architecture', 'Department of Biology', 'Department of Psychology', 'Faculty of Engineering',
    ];

    private const DEPENDENCIES = [
        'International Office', 'Registrar', 'Library', 'Student Services', 'Human Resources', 'Admissions',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $institution = Institution::query()->where('is_local', false)->inRandomOrder()->first()
            ?? Institution::create(['name' => fake()->company().' University', 'country' => 'US']);

        return $this->attributesFor($institution, fake()->randomElement(ParticipantKind::cases()));
    }

    /**
     * A visitor of the institution.
     */
    public function from(Institution $institution): static
    {
        return $this->state(fn () => $this->attributesFor($institution, fake()->randomElement(ParticipantKind::cases())));
    }

    /**
     * Spanish names, whatever the institution's country (demos in Spanish).
     */
    public function spanish(): static
    {
        return $this->state(function (array $attributes) {
            $faker = FakerFactory::create('es_ES');
            $first = $faker->firstName();
            $last = $faker->lastName();

            return [
                'name' => $first,
                'lastname' => "{$last} {$faker->lastName()}",
                'email' => fake()->unique()->numerify(Str::slug("{$first}.{$last}", '.').'.###').'@visitor.example.org',
                'phone' => '+34 '.$faker->numerify('6## ### ###'),
            ];
        });
    }

    public function ofKind(ParticipantKind $kind): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => $kind,
            'code' => fake()->numerify(strtoupper(substr($kind->value, 0, 3)).'-######'),
            'affiliation' => $this->affiliationFor($kind),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFor(Institution $institution, ParticipantKind $kind): array
    {
        $faker = FakerFactory::create(self::LOCALES[$institution->country] ?? 'en_US');
        $first = $faker->firstName();
        $last = $faker->lastName();

        return [
            'institution_id' => $institution->id,
            'kind' => $kind,
            'name' => $first,
            'lastname' => $last,
            'email' => fake()->unique()->numerify(Str::slug("{$first}.{$last}", '.').'.###').'@visitor.example.org',
            'phone' => '+'.fake()->numerify('## ### ### ####'),
            'document_number' => fake()->unique()->numerify('##########'),
            'code' => fake()->numerify(strtoupper(substr($kind->value, 0, 3)).'-######'),
            'affiliation' => $this->affiliationFor($kind),
        ];
    }

    private function affiliationFor(ParticipantKind $kind): string
    {
        return fake()->randomElement(match ($kind) {
            ParticipantKind::Student => self::PROGRAMS,
            ParticipantKind::Professor => self::DEPARTMENTS,
            ParticipantKind::Staff => self::DEPENDENCIES,
        });
    }
}
