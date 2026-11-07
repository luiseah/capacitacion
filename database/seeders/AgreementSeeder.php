<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Module\AcademicMobility\Core\Models\Institution;
use Module\Agreements\Models\Agreement;

/**
 * Demo agreements (convenios) of the partner institutions with the local one: each institution has
 * one to three, framework (marco) or specific, bilateral or multilateral, in every status (draft, in process, in force,
 * expired, suspended, terminated), with different validity and activities (training, research, extension, administrative, other; any combination).
 * The specific ones of an institution that has a framework agreement develop it. Skipped when there already are agreements.
 * Use `php artisan db:seed --class=AgreementSeeder`.
 */
class AgreementSeeder extends Seeder
{
    /**
     * Each kind of agreement: its type, name, activities, start and end (months from today, null end = indefinite),
     * what "other" is, parties, status, what it is for and how it renews.
     *
     * @var array<int, array<string, mixed>>
     */
    private const TEMPLATES = [
        ['type' => 'framework', 'name' => 'Convenio marco de cooperación', 'activities' => ['training', 'research', 'extension'], 'start' => -24, 'end' => 36, 'status' => 'active', 'renewal' => 'automatic',
            'purpose' => 'Establecer el marco general de cooperación académica, científica y cultural entre las instituciones.'],
        ['type' => 'specific', 'name' => 'Convenio específico de movilidad estudiantil', 'activities' => ['training'], 'start' => -12, 'end' => 48, 'status' => 'active', 'renewal' => 'manual',
            'purpose' => 'Regular el intercambio de estudiantes de pregrado y posgrado entre las instituciones.'],
        ['type' => 'specific', 'name' => 'Convenio específico de investigación', 'activities' => ['research'], 'start' => -36, 'end' => 24, 'status' => 'active', 'renewal' => 'manual',
            'purpose' => 'Desarrollar proyectos conjuntos de investigación y publicaciones compartidas.'],
        ['type' => 'framework', 'name' => 'Convenio marco de gestión académica', 'activities' => ['administrative'], 'start' => -12, 'end' => null, 'status' => 'active', 'renewal' => 'automatic',
            'purpose' => 'Compartir buenas prácticas de gestión académica y administrativa.'],
        ['type' => 'specific', 'name' => 'Convenio específico de movilidad docente y administrativa', 'activities' => ['training', 'administrative'], 'start' => -60, 'end' => -12, 'status' => 'expired', 'renewal' => 'none',
            'purpose' => 'Facilitar estancias cortas de profesores y personal administrativo.'],
        ['type' => 'specific', 'name' => 'Convenio específico de doble titulación', 'activities' => ['other'], 'start' => 2, 'end' => 62, 'other' => 'Programa de doble titulación', 'status' => 'in_process', 'renewal' => 'manual',
            'purpose' => 'Permitir que los estudiantes obtengan el título de ambas instituciones.'],
        ['type' => 'specific', 'name' => 'Convenio específico de investigación y formación', 'activities' => ['research', 'training'], 'start' => -6, 'end' => 30, 'status' => 'active', 'renewal' => 'manual',
            'purpose' => 'Formar investigadores a través de proyectos y estancias compartidas.'],
        ['type' => 'specific', 'name' => 'Convenio específico de extensión y proyección social', 'activities' => ['extension'], 'start' => -3, 'end' => 21, 'status' => 'active', 'renewal' => 'automatic',
            'purpose' => 'Realizar actividades conjuntas de extensión y proyección social.'],
        ['type' => 'specific', 'name' => 'Convenio multilateral de la red de cooperación universitaria', 'activities' => ['research', 'extension'], 'start' => -8, 'end' => 40, 'parties' => 'multilateral', 'status' => 'active', 'renewal' => 'manual',
            'purpose' => 'Coordinar a las instituciones de la red en investigación y extensión.'],
        ['type' => 'framework', 'name' => 'Convenio marco multilateral del consorcio de movilidad', 'activities' => ['training', 'administrative'], 'start' => -18, 'end' => null, 'parties' => 'multilateral', 'status' => 'active', 'renewal' => 'automatic',
            'purpose' => 'Reconocer mutuamente la movilidad de estudiantes y personal dentro del consorcio.'],
        ['type' => 'specific', 'name' => 'Convenio específico en elaboración', 'activities' => ['research'], 'start' => 1, 'end' => 25, 'status' => 'draft', 'renewal' => 'manual',
            'purpose' => 'Borrador de un convenio de investigación que aún se está redactando.'],
        ['type' => 'specific', 'name' => 'Convenio específico suspendido temporalmente', 'activities' => ['training'], 'start' => -14, 'end' => 10, 'status' => 'suspended', 'renewal' => 'none',
            'purpose' => 'Intercambio de estudiantes suspendido mientras se revisan las condiciones.'],
        ['type' => 'specific', 'name' => 'Convenio específico terminado anticipadamente', 'activities' => ['extension'], 'start' => -30, 'end' => 6, 'status' => 'terminated', 'renewal' => 'none',
            'purpose' => 'Actividades de extensión que las instituciones acordaron dar por terminadas.'],
    ];

    public function run(): void
    {
        // The institutions that sign them.
        $this->call(InstitutionSeeder::class);

        if (Agreement::query()->exists()) {
            $this->command?->info('agreements already seeded, skipping.');

            return;
        }

        $partners = Institution::query()->partners()->orderBy('name')->get();
        $number = 0;

        foreach ($partners as $index => $institution) {
            $created = collect();

            // One to three each, taken in turns from the templates so every kind appears.
            foreach (range(0, $index % 3) as $offset) {
                $template = self::TEMPLATES[($index + $offset) % count(self::TEMPLATES)];
                $parties = $template['parties'] ?? 'bilateral';
                $startsOn = today()->addMonths($template['start']);

                $created->push($agreement = Agreement::create([
                    'institution_id' => $institution->id,
                    'type' => $template['type'],
                    'parties' => $parties,
                    'status' => $template['status'],
                    'name' => $template['name'],
                    'reference' => sprintf('CONV-%d-%03d', $startsOn->year, ++$number),
                    'purpose' => $template['purpose'],
                    'starts_on' => $startsOn,
                    'ends_on' => $template['end'] === null ? null : today()->addMonths($template['end']),
                    // Signed a little before it starts to be in force; a draft or one in process is not signed yet.
                    'signed_on' => in_array($template['status'], ['draft', 'in_process'], true) ? null : $startsOn->copy()->subDays(15),
                    'renewal' => $template['renewal'],
                    'activities' => $template['activities'],
                    'other_activity' => $template['other'] ?? null,
                ]));

                // A multilateral agreement binds the institution with the next two of the list.
                if ($parties === 'multilateral') {
                    $agreement->syncInstitutions(array_map(
                        fn (int $step) => $partners[($index + $step) % $partners->count()]->id,
                        [1, 2],
                    ));
                }
            }

            // The specific agreements of an institution that has a framework one develop it.
            if ($framework = $created->first(fn (Agreement $agreement) => $agreement->isFramework())) {
                $created->reject(fn (Agreement $agreement) => $agreement->isFramework())
                    ->each(fn (Agreement $agreement) => $agreement->update(['framework_id' => $framework->id]));
            }
        }
    }
}
