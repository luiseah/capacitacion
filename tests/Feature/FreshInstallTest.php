<?php

namespace Tests\Feature;

use App\Models\ExternalParticipant;
use App\Models\ServiceRequest;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Models\Agreement;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\Models\MobilityRequirement;
use Module\AcademicMobility\Core\Models\Requirement;
use Tests\TestCase;

/**
 * `php artisan migrate --seed` on an empty database leaves the whole application filled.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrating_and_seeding_fills_every_table_of_the_application(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The tables that hold data (the others are runtime ones: cache, jobs, sessions, audits, tokens...).
        foreach ([
            'users', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles', 'request_types', 'requests',
            'students', 'professors', 'administrative_staff', 'external_participants',
            'academic_institutions', 'academic_requirements', 'academic_agreements', 'academic_agreement_institution',
            'academic_mobilities', 'academic_mobility_requirements',
        ] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), "{$table} is empty after seeding");
        }
    }

    public function test_the_seeded_data_is_coherent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Institution::where('is_local', true)->count());
        $this->assertSame('Universidad Santiago de Cali', Institution::local()->name);

        // Mobilities: every direction, scope and status, each with its requirements; some under an agreement in force.
        $mobilities = Mobility::with('requirements')->get();
        $this->assertSame(24, $mobilities->count());
        $this->assertEqualsCanonicalizing(
            array_map(fn (MobilityDirection $d) => $d->value, MobilityDirection::cases()),
            $mobilities->pluck('direction')->map->value->unique()->values()->all(),
        );
        $this->assertEqualsCanonicalizing(
            array_map(fn (MobilityScope $s) => $s->value, MobilityScope::cases()),
            $mobilities->pluck('scope')->map->value->unique()->values()->all(),
        );
        $this->assertEqualsCanonicalizing(
            array_map(fn (MobilityStatus $s) => $s->value, MobilityStatus::cases()),
            $mobilities->pluck('status')->map->value->unique()->values()->all(),
        );
        $this->assertSame(['professor', 'staff', 'student'], $mobilities->pluck('participant_type')->unique()->sort()->values()->all());
        $mobilities->each(fn (Mobility $mobility) => $this->assertGreaterThan(0, $mobility->requirements->count()));
        $this->assertGreaterThan(0, $mobilities->whereNotNull('agreement_id')->count());
        $this->assertGreaterThan(0, MobilityRequirement::whereNotNull('fulfilled_at')->count());

        // Every mobility keeps the rules: the local institution on one side, a partner on the other.
        $local = Institution::local();
        $mobilities->each(function (Mobility $mobility) use ($local) {
            $this->assertContains($local->id, [$mobility->origin_institution_id, $mobility->destination_institution_id]);
            $this->assertSame($mobility->direction === MobilityDirection::Outgoing, $mobility->origin_institution_id === $local->id);

            if ($mobility->agreement_id !== null) {
                $this->assertNotNull(Agreement::query()->forInstitution(
                    $mobility->direction === MobilityDirection::Incoming ? $mobility->origin_institution_id : $mobility->destination_institution_id,
                )->whereKey($mobility->agreement_id)->first());
            }
        });

        // Visitors and the university's own people never mix.
        $this->assertSame(60, ExternalParticipant::count());
        $this->assertSame(0, ExternalParticipant::where('institution_id', $local->id)->count());
        $this->assertSame(24, ServiceRequest::query()->withoutGlobalScopes()->count());
        $this->assertGreaterThan(0, Requirement::count());
    }

    public function test_seeding_again_changes_nothing_and_does_not_fail(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = fn () => collect(['users', 'students', 'professors', 'administrative_staff', 'external_participants', 'academic_institutions',
            'academic_requirements', 'academic_agreements', 'academic_mobilities', 'academic_mobility_requirements', 'requests', 'roles', 'permissions'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();

        $before = $counts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, $counts());
        $this->assertSame(1, User::where('email', 'test@example.com')->count());
    }

    public function test_demo_data_is_not_seeded_in_production(): void
    {
        $this->app['env'] = 'production';

        // `db:seed` asks for confirmation in production (and `--force` skips it); the seeder is run as that does.
        (new DatabaseSeeder)->setContainer($this->app)->run();

        // What the application needs is there; the demo people, requests and mobilities are not.
        $this->assertGreaterThan(0, DB::table('academic_institutions')->count());
        $this->assertGreaterThan(0, DB::table('academic_requirements')->count());
        $this->assertGreaterThan(0, DB::table('academic_agreements')->count());
        $this->assertSame(1, User::count());
        $this->assertSame(0, DB::table('students')->count() + DB::table('external_participants')->count() + DB::table('academic_mobilities')->count() + DB::table('requests')->count());
    }
}
