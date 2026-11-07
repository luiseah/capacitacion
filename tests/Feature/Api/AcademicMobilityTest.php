<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AdministrativeStaff;
use App\Models\ExternalParticipant;
use App\Models\Professor;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;
use Tests\TestCase;

/**
 * The academic-mobility package wired into this app: its routes, the Sanctum
 * session middleware, the Spatie permissions behind its abilities and the
 * `user` participant registered in AppServiceProvider.
 */
class AcademicMobilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingAsAdmin(): User
    {
        return Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Admin->value));
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/academic-mobility/mobilities')->assertUnauthorized();
        $this->getJson('/api/academic-mobility/institutions')->assertUnauthorized();
    }

    public function test_users_without_the_permissions_get_403(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Requester->value));

        $this->getJson('/api/academic-mobility/mobilities')->assertForbidden();
        $this->getJson('/api/academic-mobility/mobilities/options')->assertForbidden();
        $this->getJson('/api/academic-mobility/institutions')->assertForbidden();
    }

    public function test_an_admin_manages_institutions_and_creates_a_mobility(): void
    {
        $admin = $this->actingAsAdmin();
        $student = Student::factory()->for($admin)->create();

        $local = $this->postJson('/api/academic-mobility/institutions', ['name' => 'Local', 'country' => 'es', 'is_local' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_local', true)
            ->json('data.id');
        $partner = $this->postJson('/api/academic-mobility/institutions', ['name' => 'Sorbonne', 'country' => 'FR'])
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/api/academic-mobility/institutions')->assertOk()->assertJsonPath('total', 2);

        $this->getJson('/api/academic-mobility/mobilities/options')
            ->assertOk()
            ->assertJsonPath('data.participant_types', [
                'outgoing' => ['student', 'professor', 'staff'],
                'incoming' => ['student', 'professor', 'staff'],
            ])
            ->assertJsonPath('data.strategies', [['alias' => 'default', 'direction' => null]])
            ->assertJsonCount(2, 'data.institutions');

        $this->postJson('/api/academic-mobility/mobilities', [
            'participant_type' => 'student',
            'participant_id' => (string) $student->id,
            'strategy' => 'default',
            'direction' => 'outgoing',
            'scope' => 'international',
            'institution_id' => $partner,
        ])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'outgoing')
            ->assertJsonPath('data.scope', 'international')
            ->assertJsonPath('data.origin.name', 'Local');

        $this->getJson('/api/academic-mobility/mobilities')->assertOk()->assertJsonPath('total', 1);
        $this->assertSame(2, Institution::query()->count());
    }

    public function test_the_signed_in_user_is_offered_once_per_profile_with_its_details(): void
    {
        $admin = $this->actingAsAdmin();

        // An account without profiles is not a participant.
        $this->getJson('/api/academic-mobility/mobilities/options')->assertJsonPath('data.current_participants', []);

        $law = Student::factory()->for($admin)->create(['program' => 'Derecho', 'semester' => 4, 'gpa' => 4.25, 'status' => StudentStatus::Active]);
        $medicine = Student::factory()->for($admin)->create(['program' => 'Medicina']);
        $professor = Professor::factory()->for($admin)->create(['faculty' => 'Facultad de Salud']);

        $participants = collect($this->getJson('/api/academic-mobility/mobilities/options')->assertOk()->json('data.current_participants'));

        $this->assertSame(['student', 'student', 'professor'], $participants->pluck('type')->all());
        $this->assertSame([(string) $law->id, (string) $medicine->id, (string) $professor->id], $participants->pluck('id')->all());
        $this->assertSame($admin->full_name, $participants[0]['name']);

        $details = collect($participants[0]['details'])->pluck('value', 'label');
        $this->assertSame('Derecho', $details['Program']);
        $this->assertSame('4', (string) $details['Semester']);
        $this->assertSame('4.25', $details['GPA']);
        $this->assertSame($law->code, $details['Code']);
        $this->assertSame('Active', $details['Status']);

        $this->assertSame('Facultad de Salud', collect($participants[2]['details'])->pluck('value', 'label')['Faculty']);
    }

    public function test_participants_are_searched_by_kind_with_their_own_details(): void
    {
        $this->actingAsAdmin();
        $ana = User::factory()->create(['name' => 'Ana', 'lastname' => 'Quintero', 'email' => 'ana@example.com']);
        Student::factory()->for($ana)->create(['program' => 'Derecho']);
        Student::factory()->for($ana)->create(['program' => 'Medicina']);
        Professor::factory()->for(User::factory()->create(['name' => 'Ana', 'lastname' => 'Profesora']))->create();
        AdministrativeStaff::factory()->for(User::factory()->create(['name' => 'Luis', 'lastname' => 'Oficina']))->create(['dependency' => 'Tesorería']);

        // The same person appears once per program, so the programs tell them apart.
        $students = $this->getJson('/api/academic-mobility/mobilities/participants?type=student&direction=outgoing&search=quintero')->assertOk()->json('data');
        $this->assertCount(2, $students);
        $this->assertSame(['Derecho', 'Medicina'], array_map(fn ($s) => collect($s['details'])->pluck('value', 'label')['Program'], $students));
        $this->assertNotSame($students[0]['id'], $students[1]['id']);

        // Each kind searches its own table, by user data or by the profile's own data.
        $this->getJson('/api/academic-mobility/mobilities/participants?type=professor&direction=outgoing&search=ana')->assertJsonCount(1, 'data');
        $this->getJson('/api/academic-mobility/mobilities/participants?type=staff&direction=outgoing&search=tesorer')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Luis Oficina');
        $this->getJson('/api/academic-mobility/mobilities/participants?type=student&direction=outgoing&search=medicina')->assertJsonCount(1, 'data');
    }

    public function test_a_mobility_is_created_for_a_profile_of_the_kind(): void
    {
        $admin = $this->actingAsAdmin();
        $student = Student::factory()->for($admin)->create();
        $partner = Institution::create(['name' => 'Sorbonne', 'country' => 'FR']);
        Institution::create(['name' => 'Local', 'country' => 'CO', 'is_local' => true]);

        $this->postJson('/api/academic-mobility/mobilities', [
            'participant_type' => 'student',
            'participant_id' => (string) $student->id,
            'strategy' => 'default',
            'direction' => 'outgoing',
            'scope' => 'international',
            'institution_id' => $partner->id,
        ])->assertCreated()->assertJsonPath('data.participant_id', (string) $student->id);

        // A professor id is not a student id.
        $this->postJson('/api/academic-mobility/mobilities', [
            'participant_type' => 'student',
            'participant_id' => '999999',
            'strategy' => 'default',
            'direction' => 'outgoing',
            'scope' => 'international',
            'institution_id' => $partner->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('participant_id');
    }

    public function test_the_pages_render_for_an_admin(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin->value));

        $this->get('/academic-mobility/mobilities')->assertOk();
        $this->get('/academic-mobility/mobilities/create')->assertOk();
        $this->get('/academic-mobility/institutions')->assertOk();
        $this->get('/academic-mobility/institutions/create')->assertOk();
    }

    public function test_visitors_come_from_their_own_table_and_origin_institution(): void
    {
        $this->actingAsAdmin();
        $local = Institution::create(['name' => 'Local', 'country' => 'CO', 'is_local' => true]);
        $sorbonne = Institution::create(['name' => 'Sorbonne', 'country' => 'FR']);
        $toronto = Institution::create(['name' => 'Toronto', 'country' => 'CA']);

        $visitor = ExternalParticipant::factory()->from($sorbonne)->ofKind(ParticipantKind::Student)
            ->create(['name' => 'Camille', 'lastname' => 'Dubois', 'affiliation' => 'Droit']);
        ExternalParticipant::factory()->from($toronto)->ofKind(ParticipantKind::Student)->create(['name' => 'Camille', 'lastname' => 'Smith']);
        ExternalParticipant::factory()->from($sorbonne)->ofKind(ParticipantKind::Professor)->create(['name' => 'Camille', 'lastname' => 'Prof']);
        // An internal student with the same name must never show up as a visitor.
        Student::factory()->for(User::factory()->create(['name' => 'Camille', 'lastname' => 'Interna']))->create();

        $base = '/api/academic-mobility/mobilities/participants?type=student&direction=incoming&search=camille';

        // Only visitors of the chosen origin, of the chosen kind, with the institution among their details.
        $found = $this->getJson($base.'&institution_id='.$sorbonne->id)->assertOk()->assertJsonCount(1, 'data')->json('data.0');
        $this->assertSame((string) $visitor->id, $found['id']);
        $this->assertSame('Camille Dubois', $found['name']);
        $details = collect($found['details'])->pluck('value', 'label');
        $this->assertSame('Sorbonne', $details['Institution']);
        $this->assertSame('Droit', $details['Program']);
        $this->assertSame($visitor->document_number, $details['Document']);

        // The origin is what feeds the source: without it there is nothing to look in.
        $this->getJson($base)->assertUnprocessable()->assertJsonValidationErrors('institution_id');

        // A visitor arrives from their own institution, never from another one.
        $payload = ['participant_type' => 'student', 'participant_id' => (string) $visitor->id, 'strategy' => 'default', 'direction' => 'incoming'];
        $this->postJson('/api/academic-mobility/mobilities', $payload + ['scope' => 'international', 'institution_id' => $toronto->id])
            ->assertUnprocessable()->assertJsonValidationErrors('participant_id');
        $this->postJson('/api/academic-mobility/mobilities', $payload + ['scope' => 'international', 'institution_id' => $sorbonne->id])
            ->assertCreated()
            ->assertJsonPath('data.origin.name', 'Sorbonne')
            ->assertJsonPath('data.destination.id', $local->id);

        // Outgoing mobilities cannot use a visitor's id, and visitors are not in the internal source.
        $this->postJson('/api/academic-mobility/mobilities', [
            'participant_type' => 'student', 'participant_id' => (string) $visitor->id, 'strategy' => 'default',
            'direction' => 'outgoing', 'scope' => 'international', 'institution_id' => $sorbonne->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('participant_id');
    }

    public function test_a_visitor_that_does_not_exist_is_registered_from_the_mobility_form(): void
    {
        $this->actingAsAdmin();
        $local = Institution::create(['name' => 'Local', 'country' => 'CO', 'is_local' => true]);
        $sorbonne = Institution::create(['name' => 'Sorbonne', 'country' => 'FR']);

        // Only incoming mobilities register people: own people come from the university's tables.
        $forms = $this->getJson('/api/academic-mobility/mobilities/options')->assertOk()->json('data.participant_forms');
        $this->assertSame(['incoming'], array_keys($forms));
        $this->assertSame(['student', 'professor', 'staff'], array_keys($forms['incoming']));
        $this->assertSame('Program', collect($forms['incoming']['student']['fields'])->firstWhere('name', 'affiliation')['label']);
        $this->assertSame('Faculty', collect($forms['incoming']['professor']['fields'])->firstWhere('name', 'affiliation')['label']);
        $this->assertSame('Dependency', collect($forms['incoming']['staff']['fields'])->firstWhere('name', 'affiliation')['label']);

        $payload = [
            'type' => 'professor', 'direction' => 'incoming', 'institution_id' => $sorbonne->id,
            'data' => ['name' => 'Camille', 'lastname' => 'Dubois', 'document_number' => 'FR-123', 'affiliation' => 'Faculty of Law', 'email' => 'camille@sorbonne.example'],
        ];

        $created = $this->postJson('/api/academic-mobility/mobilities/participants', $payload)
            ->assertCreated()
            ->assertJsonPath('data.type', 'professor')
            ->assertJsonPath('data.name', 'Camille Dubois')
            ->json('data');

        $this->assertDatabaseHas('external_participants', ['institution_id' => $sorbonne->id, 'kind' => 'professor', 'document_number' => 'FR-123']);
        $this->assertSame(0, Student::count() + Professor::count() + User::query()->where('name', 'Camille')->count());
        $this->assertSame('Sorbonne', collect($created['details'])->pluck('value', 'label')['Institution']);

        // It is found by the origin's search, and is at once a valid participant of a mobility arriving from there.
        $this->getJson('/api/academic-mobility/mobilities/participants?type=professor&direction=incoming&search=dubois&institution_id='.$sorbonne->id)
            ->assertJsonCount(1, 'data');
        $this->postJson('/api/academic-mobility/mobilities', [
            'participant_type' => 'professor', 'participant_id' => $created['id'], 'strategy' => 'default',
            'direction' => 'incoming', 'scope' => 'international', 'institution_id' => $sorbonne->id,
        ])->assertCreated()->assertJsonPath('data.destination.id', $local->id);

        // The same document at the same institution and kind is the same person; as another kind or elsewhere it is not.
        $this->postJson('/api/academic-mobility/mobilities/participants', $payload)->assertUnprocessable()->assertJsonValidationErrors('data.document_number');
        $this->postJson('/api/academic-mobility/mobilities/participants', ['type' => 'student'] + $payload)->assertCreated();

        // Required data, and a visitor of an institution can only be registered with it.
        $this->postJson('/api/academic-mobility/mobilities/participants', ['data' => []] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['data.name', 'data.lastname', 'data.document_number', 'data.affiliation']);
        $this->postJson('/api/academic-mobility/mobilities/participants', ['institution_id' => null] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->postJson('/api/academic-mobility/mobilities/participants', ['direction' => 'outgoing'] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_the_registration_form_can_be_filled_with_a_random_spanish_visitor(): void
    {
        $this->actingAsAdmin();
        $sorbonne = Institution::create(['name' => 'Sorbonne', 'country' => 'FR']);
        $sample = fn (string $kind = 'student') => $this->getJson("/api/academic-mobility/mobilities/participants/sample?type={$kind}&direction=incoming&institution_id={$sorbonne->id}");

        // Off outside local development.
        config(['academic-mobility.samples' => false]);
        $this->getJson('/api/academic-mobility/mobilities/options')->assertJsonPath('data.participant_forms.incoming.student.can_sample', false);
        $sample()->assertForbidden();

        config(['academic-mobility.samples' => true]);
        $this->getJson('/api/academic-mobility/mobilities/options')->assertJsonPath('data.participant_forms.incoming.student.can_sample', true);

        $data = $sample()->assertOk()->json('data');

        $this->assertSame(['name', 'lastname', 'document_number', 'affiliation', 'code', 'email', 'phone'], array_keys($data));
        $this->assertStringStartsWith('+34 6', $data['phone']);
        $this->assertNotEmpty($data['name']);
        // Two surnames, as in Spanish.
        $this->assertGreaterThanOrEqual(2, count(explode(' ', $data['lastname'])));

        // The sample is valid for the registration form and creates a visitor of that institution.
        $this->postJson('/api/academic-mobility/mobilities/participants', [
            'type' => 'student', 'direction' => 'incoming', 'institution_id' => $sorbonne->id, 'data' => $data,
        ])->assertCreated();
        $this->assertDatabaseHas('external_participants', ['document_number' => $data['document_number'], 'institution_id' => $sorbonne->id]);

        // Each kind gets its own kind of affiliation, and outgoing has no samples.
        $this->assertNotEmpty($sample('professor')->assertOk()->json('data.affiliation'));
        $this->getJson('/api/academic-mobility/mobilities/participants/sample?type=student&direction=outgoing')->assertUnprocessable();
    }

    public function test_an_admin_manages_agreements_and_sees_them_when_choosing_the_institution(): void
    {
        $this->actingAsAdmin();
        Institution::create(['name' => 'Local', 'country' => 'CO', 'is_local' => true]);
        $sorbonne = Institution::create(['name' => 'Sorbonne', 'country' => 'FR']);

        $framework = $this->postJson('/api/agreements', [
            'institution_id' => $sorbonne->id, 'type' => 'framework', 'status' => 'active', 'name' => 'Convenio marco', 'reference' => 'CONV-1',
            'starts_on' => today()->subYear()->toDateString(), 'activities' => ['training', 'research', 'extension', 'administrative', 'other'],
            'other_activity' => 'Doble titulación',
        ])->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonCount(5, 'data.activities')->json('data.id');

        $this->postJson('/api/agreements', [
            'institution_id' => $sorbonne->id, 'type' => 'specific', 'status' => 'active', 'framework_id' => $framework, 'name' => 'Convenio de movilidad',
            'starts_on' => today()->subYears(3)->toDateString(), 'ends_on' => today()->subYear()->toDateString(), 'activities' => ['training'],
        ])->assertCreated()->assertJsonPath('data.status', 'expired')->assertJsonPath('data.framework.name', 'Convenio marco');

        // Choosing the institution in the mobility form shows what binds both, the latest first.
        $this->getJson('/api/agreements/institution?institution_id='.$sorbonne->id)
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Convenio marco', 'Convenio de movilidad'])
            ->assertJsonPath('data.*.status', ['active', 'expired']);

        $this->getJson('/api/agreements?status=active&activity=extension')->assertJsonCount(1, 'data');

        // The members without the permission cannot.
        Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Requester->value));
        $this->getJson('/api/agreements')->assertForbidden();
        $this->getJson('/api/agreements/institution?institution_id='.$sorbonne->id)->assertForbidden();
    }
}
