<?php

namespace App\Providers;

use App\Models\AdministrativeStaff;
use App\Models\ExternalParticipant;
use App\Models\Professor;
use App\Models\Student;
use App\Models\User;
use App\Participants\VisitorParticipants;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Infrastructure\Resolvers\EloquentParticipantResolver;
use Module\Pagination\Search\SearchInput;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        $this->registerParticipants();
    }

    /**
     * Who can take part in an academic mobility: the package never sees these models, only the
     * adapters. Where the participant comes from depends on the direction: the university's own
     * people leave (a profile of the kind: a student of one program, a professor, a staff member;
     * its id is the profile's id, so one person can take part as two students) and visitors of
     * another institution arrive (their own table).
     */
    private function registerParticipants(): void
    {
        $name = fn (Student|Professor|AdministrativeStaff $profile) => $profile->user->full_name;
        $searchUser = fn (Builder $query, string $term, array $columns) => $query->with('user')->where(
            fn (Builder $q) => $q->whereHas('user', fn (Builder $user) => $user->bySearch($term))
                ->orWhere(function (Builder $codes) use ($term, $columns) {
                    foreach ($columns as $column) {
                        // `search()` ignores case and accents, like the user search.
                        $codes->orWhereRaw("search({$column}) like search(?) escape '\\'", [SearchInput::like($term)]);
                    }
                }),
        );

        AcademicMobility::registerParticipantResolver(ParticipantKind::Student->value, fn () => new EloquentParticipantResolver(
            Student::class,
            ParticipantKind::Student->value,
            $name,
            fn (Student $student) => [
                'program' => $student->program,
                'semester' => $student->semester,
                'gpa' => $student->gpa === null ? null : (float) $student->gpa,
                'status' => $student->status->value,
            ],
            search: fn (Builder $query, string $term) => $searchUser($query, $term, ['code', 'program'])->orderBy('program')->orderBy('id'),
            details: fn (Student $student) => [
                __('Code') => $student->code,
                __('Program') => $student->program,
                __('Semester') => $student->semester,
                __('GPA') => $student->gpa,
                __('Status') => __(ucfirst($student->status->value)),
            ],
        ), MobilityDirection::Outgoing);

        AcademicMobility::registerParticipantResolver(ParticipantKind::Professor->value, fn () => new EloquentParticipantResolver(
            Professor::class,
            ParticipantKind::Professor->value,
            $name,
            fn (Professor $professor) => [
                'faculty' => $professor->faculty,
                'academic_rank' => $professor->academic_rank,
                'contract_type' => $professor->contract_type->value,
            ],
            search: fn (Builder $query, string $term) => $searchUser($query, $term, ['employee_code', 'faculty'])->orderBy('id'),
            details: fn (Professor $professor) => [
                __('Code') => $professor->employee_code,
                __('Faculty') => $professor->faculty,
                __('Academic rank') => $professor->academic_rank,
                __('Contract') => __(ucfirst(str_replace('_', ' ', $professor->contract_type->value))),
            ],
        ), MobilityDirection::Outgoing);

        AcademicMobility::registerParticipantResolver(ParticipantKind::Staff->value, fn () => new EloquentParticipantResolver(
            AdministrativeStaff::class,
            ParticipantKind::Staff->value,
            $name,
            fn (AdministrativeStaff $staff) => ['dependency' => $staff->dependency, 'position' => $staff->position],
            search: fn (Builder $query, string $term) => $searchUser($query, $term, ['employee_code', 'dependency'])->orderBy('id'),
            details: fn (AdministrativeStaff $staff) => [
                __('Code') => $staff->employee_code,
                __('Dependency') => $staff->dependency,
                __('Position') => $staff->position,
            ],
        ), MobilityDirection::Outgoing);

        // Incoming mobilities receive visitors, who live in their own table: never the internal people above.
        foreach (ParticipantKind::cases() as $kind) {
            AcademicMobility::registerParticipantResolver($kind->value, fn () => new VisitorParticipants($kind), MobilityDirection::Incoming);
        }

        // The package cannot see the visitors table, so it asks before deleting an institution.
        AcademicMobility::preventInstitutionDeletionUsing(fn (Institution $institution) => ExternalParticipant::query()->where('institution_id', $institution->id)->exists()
            ? __('This institution has visitors and cannot be deleted.')
            : null);

        // The signed-in user is the default participant of the mobility form, once for every
        // profile they have (a person can be a professor and study two programs).
        AcademicMobility::resolveCurrentParticipantUsing(fn (User $user) => [
            ...$user->students()->orderBy('id')->pluck('id')->map(fn (int $id) => new ParticipantReference(ParticipantKind::Student->value, (string) $id)),
            ...array_filter([
                ($id = $user->professor()->value('id')) ? new ParticipantReference(ParticipantKind::Professor->value, (string) $id) : null,
                ($id = $user->administrativeStaff()->value('id')) ? new ParticipantReference(ParticipantKind::Staff->value, (string) $id) : null,
            ]),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
