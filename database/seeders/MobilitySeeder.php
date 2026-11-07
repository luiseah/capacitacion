<?php

namespace Database\Seeders;

use App\Models\AdministrativeStaff;
use App\Models\ExternalParticipant;
use App\Models\Professor;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Module\AcademicMobility\Core\DTOs\CreateMobilityData;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\Agreements\Models\Agreement;

/**
 * Demo mobilities: every combination of direction (outgoing, incoming), scope (national, international)
 * and participant kind (student, professor, staff), twice, created through the package itself, so each one
 * has its requirements and, when the institution has an agreement in force, the agreement it is made under.
 * They are left in every status (draft to completed) and spread over the last two months.
 * Skipped when there already are mobilities. Use `php artisan db:seed --class=MobilitySeeder`.
 */
class MobilitySeeder extends Seeder
{
    /** How many mobilities each combination gets. */
    private const ROUNDS = 2;

    /** The status each mobility ends in, taken in turns; the path to get there is checked by the package. */
    private const PATHS = [
        [],
        [MobilityStatus::Submitted],
        [MobilityStatus::Submitted, MobilityStatus::UnderReview],
        [MobilityStatus::Submitted, MobilityStatus::UnderReview, MobilityStatus::Approved],
        [MobilityStatus::Submitted, MobilityStatus::UnderReview, MobilityStatus::Rejected],
        [MobilityStatus::Submitted, MobilityStatus::UnderReview, MobilityStatus::Approved, MobilityStatus::Completed],
        [MobilityStatus::Cancelled],
        [],
    ];

    public function run(): void
    {
        // What a mobility is made of: institutions, the catalog of requirements, agreements and people.
        $this->call([
            InstitutionSeeder::class,
            RequirementSeeder::class,
            AgreementSeeder::class,
            StudentSeeder::class,
            ProfessorSeeder::class,
            AdministrativeStaffSeeder::class,
            ExternalParticipantSeeder::class,
        ]);

        if (Mobility::query()->exists()) {
            $this->command?->info('mobilities already seeded, skipping.');

            return;
        }

        $local = Institution::local();
        $partners = Institution::query()->partners()->orderBy('name')->get();
        $n = 0;

        foreach (range(1, self::ROUNDS) as $round) {
            foreach ([MobilityDirection::Outgoing, MobilityDirection::Incoming] as $direction) {
                foreach ([MobilityScope::National, MobilityScope::International] as $scope) {
                    foreach (ParticipantKind::cases() as $kind) {
                        $this->mobility($direction, $scope, $kind, $local, $partners, $n++);
                    }
                }
            }
        }
    }

    /**
     * @param  Collection<int, Institution>  $partners
     */
    private function mobility(MobilityDirection $direction, MobilityScope $scope, ParticipantKind $kind, Institution $local, Collection $partners, int $n): void
    {
        $sameCountry = fn (Institution $institution) => ($institution->country === $local->country) === ($scope === MobilityScope::National);

        if ($direction === MobilityDirection::Outgoing) {
            // Own people leave for a partner of the scope: those that go under an agreement (every other
            // one) to a partner that has one in force, the rest to any partner.
            $matching = $partners->filter($sameCountry)->values();
            $covered = $matching->filter(fn (Institution $candidate) => $this->agreementIn($candidate) !== null)->values();
            $choices = $n % 2 === 0 && $covered->isNotEmpty() ? $covered : $matching;
            $institution = $choices[intdiv($n, 2) % $choices->count()];
            $participant = $this->internal($kind, $n);
        } else {
            // Visitors arrive from the institution they belong to, which has to be of the scope.
            $visitors = ExternalParticipant::query()->where('kind', $kind)->with('institution')->orderBy('id')->get()
                ->filter(fn (ExternalParticipant $visitor) => $sameCountry($visitor->institution))->values();
            $visitor = $visitors[$n % $visitors->count()];
            $institution = $visitor->institution;
            $participant = $visitor->id;
        }

        // About half of them are made under an agreement in force.
        $agreement = $n % 2 === 0 ? $this->agreementIn($institution) : null;

        $mobility = AcademicMobility::create(new CreateMobilityData(
            new ParticipantReference($kind->value, (string) $participant),
            $direction,
            $scope,
            $institution->id,
            agreementId: $agreement?->id,
        ));

        $path = self::PATHS[$n % count(self::PATHS)];

        foreach ($path as $status) {
            $mobility = AcademicMobility::transition($mobility, $status);
        }

        $this->fulfil($mobility);

        // Spread over the last two months, the oldest first.
        $created = now()->subDays(60 - ($n * 2 + 1));
        $mobility->forceFill(['created_at' => $created, 'updated_at' => $created->copy()->addDays(min(count($path) * 3, 20))])->saveQuietly();
    }

    /** The id of a profile of the kind among the university's own people. */
    private function internal(ParticipantKind $kind, int $n): int
    {
        $ids = match ($kind) {
            ParticipantKind::Student => Student::query()->orderBy('id')->pluck('id'),
            ParticipantKind::Professor => Professor::query()->orderBy('id')->pluck('id'),
            ParticipantKind::Staff => AdministrativeStaff::query()->orderBy('id')->pluck('id'),
        };

        return $ids[$n % $ids->count()];
    }

    /** An agreement in force the institution takes part in (its own or a multilateral one), if any. */
    private function agreementIn(Institution $institution): ?Agreement
    {
        return Agreement::query()->forInstitution($institution->id)->inForce()->latestFirst()->first();
    }

    /**
     * The further a request has gone, the more of its requirements are fulfilled: all for the approved and
     * completed ones, the mandatory half while it is under review.
     */
    private function fulfil(Mobility $mobility): void
    {
        $requirements = $mobility->requirements()->get();

        $done = match ($mobility->status) {
            MobilityStatus::Approved, MobilityStatus::Completed => $requirements,
            MobilityStatus::UnderReview => $requirements->where('is_mandatory', true)->take((int) ceil($requirements->where('is_mandatory', true)->count() / 2)),
            default => collect(),
        };

        $done->each(fn ($requirement) => $requirement->update(['fulfilled_at' => now()]));
    }
}
