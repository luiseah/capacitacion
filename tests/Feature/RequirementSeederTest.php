<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\InstitutionSeeder;
use Database\Seeders\RequirementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Requirement;
use Tests\TestCase;

class RequirementSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function names(MobilityDirection $direction, MobilityScope $scope, ParticipantKind $kind): array
    {
        return Requirement::query()->applicableTo($direction, $scope, $kind)->ordered()->pluck('name')->all();
    }

    public function test_the_catalog_gives_each_kind_of_mobility_its_own_requirements(): void
    {
        $this->seed(RequirementSeeder::class);

        $outgoing = $this->names(MobilityDirection::Outgoing, MobilityScope::International, ParticipantKind::Student);
        $incoming = $this->names(MobilityDirection::Incoming, MobilityScope::International, ParticipantKind::Student);

        // Direction: each side has its own paperwork.
        $this->assertContains('Endorsement from the home academic unit', $outgoing);
        $this->assertNotContains('Endorsement from the home academic unit', $incoming);
        $this->assertContains('Acceptance letter from the host unit', $incoming);
        $this->assertNotContains('Acceptance letter from the host unit', $outgoing);

        // Scope: international asks for passport and insurance, national does not.
        $national = $this->names(MobilityDirection::Outgoing, MobilityScope::National, ParticipantKind::Student);
        $this->assertContains('Valid passport', $outgoing);
        $this->assertNotContains('Valid passport', $national);
        $this->assertContains('Proof of health coverage', $national);

        // Kind: students bring a transcript, professors a CV, staff a work plan.
        $this->assertContains('Academic transcript', $outgoing);
        $this->assertContains('Updated CV', $this->names(MobilityDirection::Outgoing, MobilityScope::International, ParticipantKind::Professor));
        $this->assertContains('Work plan for the stay', $this->names(MobilityDirection::Outgoing, MobilityScope::National, ParticipantKind::Staff));
        $this->assertNotContains('Academic transcript', $this->names(MobilityDirection::Outgoing, MobilityScope::International, ParticipantKind::Professor));

        // The kind of the visitor changes what is asked on arrival.
        $this->assertContains('Proof of enrollment at the origin institution', $incoming);
        $this->assertNotContains('Proof of enrollment at the origin institution', $this->names(MobilityDirection::Incoming, MobilityScope::International, ParticipantKind::Professor));
        $this->assertContains('Proof of employment at the origin institution', $this->names(MobilityDirection::Incoming, MobilityScope::International, ParticipantKind::Professor));

        $this->assertNotEquals($outgoing, $incoming);
        $this->assertSame(23, Requirement::count());
    }

    public function test_requirements_come_with_their_spanish_texts_and_the_seeder_runs_once(): void
    {
        $this->seed(RequirementSeeder::class);
        $this->seed(RequirementSeeder::class);

        $this->assertSame(23, Requirement::count());

        $passport = Requirement::where('name', 'Valid passport')->first();
        $this->assertSame('Pasaporte vigente', $passport->localizedName('es'));
        $this->assertSame('Valid passport', $passport->localizedName('en'));
        $this->assertFalse(Requirement::where('name', 'Language proficiency certificate')->first()->is_mandatory);
    }

    public function test_a_request_shows_the_requirements_of_its_kind_in_the_language_of_the_user(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, InstitutionSeeder::class, RequirementSeeder::class]);
        $admin = Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Admin->value));
        $student = Student::factory()->for($admin)->create();
        $partner = Institution::where('country', 'FR')->first();

        $payload = [
            'participant_type' => 'student', 'participant_id' => (string) $student->id, 'strategy' => 'default',
            'direction' => 'outgoing', 'scope' => 'international', 'institution_id' => $partner->id,
        ];

        $names = $this->withCredentials()->withUnencryptedCookie('locale', 'es')
            ->postJson('/api/academic-mobility/mobilities', $payload)
            ->assertCreated()
            ->json('data.requirements.*.name');

        $this->assertContains('Aval de la unidad académica de origen', $names);
        $this->assertContains('Pasaporte vigente', $names);
        $this->assertContains('Certificado de notas', $names);
        $this->assertNotContains('Carta de aceptación de la unidad receptora', $names);

        // The preview of the form lists the same ones before the request exists.
        $this->getJson('/api/academic-mobility/mobilities/requirements?direction=outgoing&scope=international&type=student')
            ->assertOk()
            ->assertJsonCount(count($names), 'data');
    }
}
