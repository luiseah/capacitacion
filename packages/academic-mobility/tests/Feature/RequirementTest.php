<?php

namespace Module\AcademicMobility\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Module\AcademicMobility\Authorization\MobilityAbility;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\Models\Requirement;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Tests\Support\ArrayParticipantResolver;
use Module\AcademicMobility\Tests\Support\CreatesInstitutions;
use Module\AcademicMobility\Tests\Support\TestUser;
use Module\AcademicMobility\Tests\TestCase;

class RequirementTest extends TestCase
{
    use CreatesInstitutions;

    private const API = '/api/academic-mobility';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstitutions();
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([1 => ['enrollment' => 'active']]));
        AcademicMobility::registerParticipantResolver('professor', new ArrayParticipantResolver([1 => []], 'professor'));
    }

    private function actingWith(MobilityAbility ...$abilities): void
    {
        $granted = array_map(fn (MobilityAbility $a) => $a->value, $abilities);

        foreach (MobilityAbility::cases() as $ability) {
            Gate::define($ability->value, fn ($user) => in_array($ability->value, $granted, true));
        }

        $this->actingAs((new TestUser)->forceFill(['id' => 1]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requirement(string $name, array $attributes = []): Requirement
    {
        return Requirement::create(['name' => $name, ...$attributes]);
    }

    /** The catalog the tests share: one requirement per way of matching. */
    private function catalog(): void
    {
        $this->requirement('Agreement', ['position' => 1]);                                                  // any mobility
        $this->requirement('Acceptance letter', ['direction' => 'incoming', 'position' => 2]);             // by direction
        $this->requirement('Endorsement', ['direction' => 'outgoing', 'position' => 3]);
        $this->requirement('Passport', ['scope' => 'international', 'position' => 4]);                      // by scope
        $this->requirement('Identity card', ['scope' => 'national', 'position' => 5]);
        $this->requirement('Transcript', ['participant_kind' => 'student', 'position' => 6]);               // by kind
        $this->requirement('CV', ['participant_kind' => 'professor', 'position' => 7]);
        $this->requirement('Language certificate', ['direction' => 'outgoing', 'scope' => 'international', 'participant_kind' => 'student', 'position' => 8]);
        $this->requirement('Retired', ['is_active' => false, 'position' => 9]);
    }

    /**
     * @return array<int, string>
     */
    private function applicable(MobilityDirection $direction, MobilityScope $scope, ParticipantKind $kind): array
    {
        return Requirement::query()->applicableTo($direction, $scope, $kind)->ordered()->pluck('name')->all();
    }

    public function test_requirements_depend_on_direction_scope_and_participant_kind(): void
    {
        $this->catalog();

        $this->assertSame(
            ['Agreement', 'Endorsement', 'Passport', 'Transcript', 'Language certificate'],
            $this->applicable(MobilityDirection::Outgoing, MobilityScope::International, ParticipantKind::Student),
        );
        $this->assertSame(
            ['Agreement', 'Acceptance letter', 'Passport', 'Transcript'],
            $this->applicable(MobilityDirection::Incoming, MobilityScope::International, ParticipantKind::Student),
        );
        $this->assertSame(
            ['Agreement', 'Endorsement', 'Identity card', 'Transcript'],
            $this->applicable(MobilityDirection::Outgoing, MobilityScope::National, ParticipantKind::Student),
        );
        $this->assertSame(
            ['Agreement', 'Acceptance letter', 'Identity card', 'CV'],
            $this->applicable(MobilityDirection::Incoming, MobilityScope::National, ParticipantKind::Professor),
        );
        // A staff member has none of the kind-specific ones; inactive requirements never apply.
        $this->assertSame(
            ['Agreement', 'Endorsement', 'Passport'],
            $this->applicable(MobilityDirection::Outgoing, MobilityScope::International, ParticipantKind::Staff),
        );
    }

    public function test_the_creation_form_previews_the_requirements_in_the_language_of_the_request(): void
    {
        $this->requirement('Passport', ['scope' => 'international', 'translations' => ['es' => ['name' => 'Pasaporte', 'description' => 'Vigente']]]);
        $this->actingWith(MobilityAbility::Create);

        $url = self::API.'/mobilities/requirements?direction=outgoing&scope=international&type=student';

        $this->getJson($url)->assertOk()->assertJsonPath('data.0.name', 'Passport')->assertJsonPath('data.0.is_mandatory', true);

        app()->setLocale('es');
        $this->getJson($url)->assertJsonPath('data.0.name', 'Pasaporte')->assertJsonPath('data.0.description', 'Vigente');

        $this->getJson(self::API.'/mobilities/requirements?direction=outgoing&scope=national&type=student')->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/mobilities/requirements')->assertUnprocessable()->assertJsonValidationErrors(['direction', 'scope', 'type']);

        $this->actingWith(MobilityAbility::ViewAny);
        $this->getJson($url)->assertForbidden();
    }

    public function test_a_mobility_keeps_a_copy_of_its_requirements(): void
    {
        $this->catalog();
        $this->actingWith(MobilityAbility::Create, MobilityAbility::View);

        $created = $this->postJson(self::API.'/mobilities', [
            'participant_type' => 'student', 'participant_id' => '1', 'strategy' => 'default',
            'direction' => 'outgoing', 'scope' => 'international', 'institution_id' => $this->foreign->id,
        ])->assertCreated();

        $this->assertSame(
            ['Agreement', 'Endorsement', 'Passport', 'Transcript', 'Language certificate'],
            array_column($created->json('data.requirements'), 'name'),
        );
        $this->assertSame(['fulfilled' => false], ['fulfilled' => $created->json('data.requirements.0.fulfilled')]);

        // Editing or deleting the catalog afterwards does not change the request that already exists.
        Requirement::query()->where('name', 'Passport')->update(['name' => 'Passport (renamed)']);
        Requirement::query()->where('name', 'Endorsement')->delete();
        $this->requirement('Brand new', ['position' => 0]);

        $this->getJson(self::API.'/mobilities/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.participant.name', 'Participant 1')
            ->assertJsonPath('data.origin.name', 'Universidad Local')
            ->assertJsonCount(5, 'data.requirements')
            ->assertJsonPath('data.requirements.2.name', 'Passport');

        // The same combination gets today's catalog for a new request.
        $this->assertSame(['Brand new', 'Agreement', 'Passport (renamed)', 'Transcript', 'Language certificate'], $this->applicable(MobilityDirection::Outgoing, MobilityScope::International, ParticipantKind::Student));
    }

    public function test_incoming_and_outgoing_mobilities_get_different_requirements(): void
    {
        $this->catalog();
        $this->actingWith(MobilityAbility::Create);
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([1 => []]));

        $names = fn (string $direction) => array_column($this->postJson(self::API.'/mobilities', [
            'participant_type' => 'student', 'participant_id' => '1', 'strategy' => 'default',
            'direction' => $direction, 'scope' => 'international', 'institution_id' => $this->foreign->id,
        ])->assertCreated()->json('data.requirements'), 'name');

        $this->assertContains('Endorsement', $names('outgoing'));
        $this->assertNotContains('Acceptance letter', $names('outgoing'));
        $this->assertContains('Acceptance letter', $names('incoming'));
        $this->assertNotContains('Endorsement', $names('incoming'));
    }

    public function test_viewing_a_mobility_needs_the_ability(): void
    {
        $mobility = Mobility::create([
            'participant_type' => 'student', 'participant_id' => '1', 'strategy' => 'default', 'status' => 'draft',
            'direction' => 'outgoing', 'scope' => 'international',
            'origin_institution_id' => $this->local->id, 'destination_institution_id' => $this->foreign->id,
        ]);

        $this->actingWith(MobilityAbility::ViewAny);
        $this->getJson(self::API.'/mobilities/'.$mobility->id)->assertForbidden();
        $this->getJson(self::API.'/mobilities/missing')->assertNotFound();

        $this->actingWith(MobilityAbility::View);
        $this->getJson(self::API.'/mobilities/'.$mobility->id)->assertOk()->assertJsonPath('data.id', $mobility->id);
    }

    public function test_the_catalog_is_managed_with_abilities(): void
    {
        $this->actingWith();
        $this->getJson(self::API.'/requirements')->assertForbidden();
        $this->postJson(self::API.'/requirements', ['name' => 'X'])->assertForbidden();

        $this->actingWith(MobilityAbility::CreateRequirements, MobilityAbility::ViewAnyRequirements, MobilityAbility::UpdateRequirements, MobilityAbility::DeleteRequirements);

        $id = $this->postJson(self::API.'/requirements', [
            'name' => 'Passport', 'direction' => 'incoming', 'scope' => 'international', 'participant_kind' => null,
            'translations' => ['es' => ['name' => 'Pasaporte', 'description' => ''], 'fr' => ['name' => '', 'description' => '']],
        ])->assertCreated()
            ->assertJsonPath('data.direction', 'incoming')
            ->assertJsonPath('data.participant_kind', null)
            ->assertJsonPath('data.is_mandatory', true)
            ->assertJsonPath('data.translations', ['es' => ['name' => 'Pasaporte']])
            ->json('data.id');

        $this->getJson(self::API.'/requirements/'.$id)->assertOk()->assertJsonPath('data.source.name', 'Passport');

        $this->putJson(self::API.'/requirements/'.$id, ['name' => 'Valid passport', 'is_mandatory' => false, 'scope' => 'international'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Valid passport')
            ->assertJsonPath('data.is_mandatory', false)
            ->assertJsonPath('data.direction', null);

        $this->getJson(self::API.'/requirements?search=passport&scope=international&direction=incoming')->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/requirements?search=passport&scope=international')->assertJsonCount(1, 'data');

        $this->deleteJson(self::API.'/requirements/'.$id)->assertOk();
        $this->assertSoftDeleted(Requirement::withTrashed()->find($id));
    }

    public function test_the_catalog_validates_its_input(): void
    {
        $this->actingWith(MobilityAbility::CreateRequirements);

        $this->postJson(self::API.'/requirements', [])->assertJsonValidationErrors('name');
        $this->postJson(self::API.'/requirements', ['name' => 'X', 'direction' => 'sideways', 'scope' => 'galactic', 'participant_kind' => 'alien'])
            ->assertJsonValidationErrors(['direction', 'scope', 'participant_kind']);
    }

    public function test_the_catalog_can_be_filtered_by_what_each_requirement_names(): void
    {
        $this->catalog();
        $this->actingWith(MobilityAbility::ViewAnyRequirements);

        $names = fn (string $query) => collect($this->getJson(self::API.'/requirements?'.$query)->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Acceptance letter'], $names('direction=incoming'));
        $this->assertSame(['Passport', 'Language certificate'], $names('scope=international'));
        $this->assertSame(['Transcript', 'Language certificate'], $names('participant_kind=student'));
        $this->assertSame(['Retired'], $names('active=0'));
        $this->assertCount(8, $names('active=1'));
    }
}
