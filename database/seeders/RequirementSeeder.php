<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Module\AcademicMobility\Core\Models\Requirement;

/**
 * The catalog of requirements of the academic mobilities. Each one names the criteria it applies to
 * (direction, scope, participant kind); a criterion it leaves out matches anything, so a mobility
 * brings the sum of the requirements that match its direction, scope and kind, and incoming and
 * outgoing ones do not bring the same. English texts with their Spanish translation.
 * Skipped when the catalog already has requirements.
 * Use `php artisan db:seed --class=RequirementSeeder`.
 */
class RequirementSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: array<string, ?string>, 5?: bool}>
     *                                                                                                       name, description, Spanish name, Spanish description, criteria, mandatory
     */
    private const CATALOG = [
        // Any mobility.
        ['Inter-institutional agreement in force', 'A signed agreement between both institutions covering the exchange.', 'Convenio interinstitucional vigente', 'Convenio firmado entre ambas instituciones que cubra el intercambio.', []],
        ['Application form', 'The institutional application form, completed and signed.', 'Formulario de solicitud', 'Formulario institucional de solicitud, diligenciado y firmado.', []],
        ['Copy of the identity document', 'Both sides of the identity card (or the passport page with the photo).', 'Copia del documento de identidad', 'Cédula por ambas caras (o la página del pasaporte con la foto).', []],

        // By direction.
        ['Endorsement from the home academic unit', 'Letter from the program or dependency that authorizes the absence.', 'Aval de la unidad académica de origen', 'Carta del programa o dependencia que autoriza la ausencia.', ['direction' => 'outgoing']],
        ['Acceptance letter from the host unit', 'Letter from the unit at this university that agrees to receive the visitor.', 'Carta de aceptación de la unidad receptora', 'Carta de la unidad de esta universidad que acepta recibir al visitante.', ['direction' => 'incoming']],
        ['Nomination from the origin institution', 'Official nomination signed by the international office of the origin institution.', 'Postulación de la institución de origen', 'Postulación oficial firmada por la oficina de relaciones internacionales de la institución de origen.', ['direction' => 'incoming']],

        // By scope.
        ['Valid passport', 'Passport valid for at least six months after the end of the stay.', 'Pasaporte vigente', 'Pasaporte vigente al menos seis meses después de terminar la estadía.', ['scope' => 'international']],
        ['Visa or entry permit', 'Visa or entry permit for the destination country, when it is required.', 'Visa o permiso de ingreso', 'Visa o permiso de ingreso al país de destino, cuando se exija.', ['scope' => 'international']],
        ['Travel and health insurance', 'International insurance covering health and repatriation for the whole stay.', 'Seguro médico y de viaje', 'Seguro internacional que cubra salud y repatriación durante toda la estadía.', ['scope' => 'international']],
        ['Proof of health coverage', 'Certificate of the health insurance (EPS or equivalent) in force.', 'Certificado de afiliación a salud', 'Certificado de la EPS (o equivalente) vigente.', ['scope' => 'national']],

        // By participant kind.
        ['Academic transcript', 'Official record of grades, with the cumulative GPA.', 'Certificado de notas', 'Certificado oficial de notas, con el promedio acumulado.', ['participant_kind' => 'student']],
        ['Proof of enrollment', 'Certificate that the student is currently enrolled.', 'Certificado de matrícula vigente', 'Certificado de que el estudiante está matriculado actualmente.', ['participant_kind' => 'student']],
        ['Updated CV', 'Curriculum vitae with the academic and research record.', 'Hoja de vida actualizada', 'Hoja de vida con la trayectoria académica e investigativa.', ['participant_kind' => 'professor']],
        ['Plan of teaching or research activities', 'What the professor will teach or research during the stay.', 'Plan de actividades docentes o de investigación', 'Lo que el profesor enseñará o investigará durante la estadía.', ['participant_kind' => 'professor']],
        ['Work plan for the stay', 'Objectives and activities of the staff member during the stay.', 'Plan de trabajo de la estadía', 'Objetivos y actividades del administrativo durante la estadía.', ['participant_kind' => 'staff']],

        // Direction and kind.
        ['Study plan approved by the program director', 'Courses to take at the host institution, with their equivalences.', 'Plan de estudios aprobado por el director del programa', 'Asignaturas a cursar en la institución receptora, con sus equivalencias.', ['direction' => 'outgoing', 'participant_kind' => 'student']],
        ['Authorization from the dean', 'Signed authorization of the dean of the faculty.', 'Autorización del decano', 'Autorización firmada por el decano de la facultad.', ['direction' => 'outgoing', 'participant_kind' => 'professor']],
        ['Authorization from the immediate supervisor', 'Signed authorization of the supervisor and of human resources.', 'Autorización del jefe inmediato', 'Autorización firmada por el jefe inmediato y por talento humano.', ['direction' => 'outgoing', 'participant_kind' => 'staff']],
        ['Proof of enrollment at the origin institution', 'Certificate from the institution the student comes from.', 'Certificado de matrícula en la institución de origen', 'Certificado de la institución de la que viene el estudiante.', ['direction' => 'incoming', 'participant_kind' => 'student']],
        ['Proof of employment at the origin institution', 'Certificate of the current contract at the institution of origin.', 'Certificado laboral de la institución de origen', 'Certificado del contrato vigente en la institución de origen.', ['direction' => 'incoming', 'participant_kind' => 'professor']],
        ['Proof of employment at the origin institution (staff)', 'Certificate of the current contract at the institution of origin.', 'Certificado laboral de la institución de origen (administrativo)', 'Certificado del contrato vigente en la institución de origen.', ['direction' => 'incoming', 'participant_kind' => 'staff']],

        // Direction, scope and kind.
        ['Language proficiency certificate', 'Certificate of the level of the language of the host country (B1 or higher).', 'Certificado de suficiencia en idioma', 'Certificado del nivel del idioma del país receptor (B1 o superior).', ['direction' => 'outgoing', 'scope' => 'international', 'participant_kind' => 'student'], false],
        ['Certified translation of the documents', 'Official translation of the documents not issued in Spanish.', 'Traducción oficial de los documentos', 'Traducción oficial de los documentos que no estén en español.', ['direction' => 'incoming', 'scope' => 'international']],
    ];

    public function run(): void
    {
        if (Requirement::query()->exists()) {
            $this->command?->info('requirements already seeded, skipping.');

            return;
        }

        foreach (self::CATALOG as $position => $entry) {
            [$name, $description, $nameEs, $descriptionEs, $criteria] = $entry;
            $mandatory = $entry[5] ?? true;

            Requirement::create([
                'name' => $name,
                'description' => $description,
                'direction' => $criteria['direction'] ?? null,
                'scope' => $criteria['scope'] ?? null,
                'participant_kind' => $criteria['participant_kind'] ?? null,
                'is_mandatory' => $mandatory,
                'position' => ($position + 1) * 10,
                'translations' => ['es' => ['name' => $nameEs, 'description' => $descriptionEs]],
            ]);
        }
    }
}
