<?php

namespace Module\AcademicMobility\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Module\AcademicMobility\Authorization\MobilityAbility;
use Module\AcademicMobility\Contracts\AgreementValidator;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Exceptions\AgreementNotApplicable;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Tests\Support\AbroadCondition;
use Module\AcademicMobility\Tests\Support\ActiveEnrollmentRule;
use Module\AcademicMobility\Tests\Support\ArrayParticipantResolver;
use Module\AcademicMobility\Tests\Support\CreatableArrayResolver;
use Module\AcademicMobility\Tests\Support\CreatesInstitutions;
use Module\AcademicMobility\Tests\Support\ExchangeStrategy;
use Module\AcademicMobility\Tests\Support\OutgoingExchangeStrategy;
use Module\AcademicMobility\Tests\Support\TestUser;
use Module\AcademicMobility\Tests\TestCase;

class MobilityApiTest extends TestCase
{
    use CreatesInstitutions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstitutions();
    }

    private const URL = '/api/academic-mobility/mobilities';

    private function userWith(MobilityAbility ...$abilities): TestUser
    {
        $granted = array_map(fn (MobilityAbility $a) => $a->value, $abilities);

        foreach (MobilityAbility::cases() as $ability) {
            Gate::define($ability->value, fn ($user) => in_array($ability->value, $granted, true));
        }

        return (new TestUser)->forceFill(['id' => 1]);
    }

    private function mobility(array $attributes = []): Mobility
    {
        return Mobility::create([
            'participant_type' => 'student',
            'participant_id' => '1',
            'strategy' => 'default',
            'status' => MobilityStatus::Draft,
            ...$attributes,
        ]);
    }

    public function test_guests_get_401(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_users_without_the_ability_get_403(): void
    {
        $this->actingAs($this->userWith())->getJson(self::URL)->assertForbidden();
    }

    public function test_lists_mobilities_in_the_api_envelope(): void
    {
        $this->mobility();

        $this->actingAs($this->userWith(MobilityAbility::ViewAny))
            ->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.participant_type', 'student')
            ->assertJsonStructure(['data' => [['id', 'participant_type', 'participant_id', 'strategy', 'status', 'is_final', 'created_at']], 'current_page', 'last_page', 'per_page']);
    }

    public function test_filters_by_status_and_participant_type(): void
    {
        $this->mobility(['status' => MobilityStatus::Draft]);
        $this->mobility(['status' => MobilityStatus::Approved]);
        $this->mobility(['status' => MobilityStatus::Approved, 'participant_type' => 'professor']);

        $this->actingAs($this->userWith(MobilityAbility::ViewAny));

        $this->getJson(self::URL.'?status=approved')->assertJsonPath('total', 2);
        $this->getJson(self::URL.'?status=approved&participant_type=professor')->assertJsonPath('total', 1);
    }

    public function test_sorts_and_defaults_to_newest_first(): void
    {
        $old = $this->mobility(['strategy' => 'a']);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $new = $this->mobility(['strategy' => 'b']);

        $this->actingAs($this->userWith(MobilityAbility::ViewAny));

        $this->getJson(self::URL)->assertJsonPath('data.0.id', $new->id);
        $this->getJson(self::URL.'?sort[created_at]=asc')->assertJsonPath('data.0.id', $old->id);
    }

    public function test_rejects_invalid_filters(): void
    {
        $this->actingAs($this->userWith(MobilityAbility::ViewAny));

        $this->getJson(self::URL.'?status=nope')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson(self::URL.'?sort[password]=asc')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson(self::URL.'?sort[created_at]=sideways')->assertUnprocessable();
    }

    public function test_route_is_named(): void
    {
        $this->assertNotNull(app('router')->getRoutes()->getByName('academic-mobility.api.mobilities.index'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'participant_type' => 'student',
            'participant_id' => '1',
            'direction' => 'outgoing',
            'scope' => 'international',
            'institution_id' => $this->foreign->id,
            'strategy' => 'exchange',
            ...$overrides,
        ];
    }

    private function registerStudents(): void
    {
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([
            '1' => ['enrollment' => 'active', 'gpa' => 4.5],
            '2' => ['enrollment' => 'inactive', 'gpa' => 4.5],
        ]));
        AcademicMobility::registerRule('active_enrollment', ActiveEnrollmentRule::class);
        AcademicMobility::registerConditionEvaluator('abroad', AbroadCondition::class);
        AcademicMobility::registerStrategy('exchange', ExchangeStrategy::class);
        AcademicMobility::registerStrategy('outgoing_exchange', OutgoingExchangeStrategy::class);
    }

    public function test_store_requires_the_create_ability(): void
    {
        $this->registerStudents();

        $this->actingAs($this->userWith(MobilityAbility::ViewAny))
            ->postJson(self::URL, $this->payload())
            ->assertForbidden();
    }

    public function test_an_agreement_is_refused_without_who_validates_it_or_when_the_validator_refuses_it(): void
    {
        $this->registerStudents();
        $user = $this->userWith(MobilityAbility::Create);

        // Nobody manages agreements: a mobility cannot be made under one.
        $this->actingAs($user)->postJson(self::URL, $this->payload(['agreement_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ']))
            ->assertUnprocessable()->assertJsonValidationErrors('agreement_id');

        $this->app->bind(AgreementValidator::class, fn () => new class implements AgreementValidator
        {
            public function validate(string $agreementId, Institution $partner): void
            {
                throw AgreementNotApplicable::notInForce();
            }
        });

        $this->actingAs($user)->postJson(self::URL, $this->payload(['agreement_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ']))
            ->assertUnprocessable()->assertJsonPath('errors.agreement_id.0', 'The agreement is not in force: only agreements in force can be used.');
        $this->assertDatabaseCount('academic_mobilities', 0);
    }

    public function test_creates_a_draft_mobility(): void
    {
        $this->registerStudents();

        $this->actingAs($this->userWith(MobilityAbility::Create))
            ->postJson(self::URL, $this->payload([
                'strategy' => 'exchange',
                'metadata' => ['destination' => 'abroad'],
            ]))
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.strategy', 'exchange')
            ->assertJsonPath('data.direction', 'outgoing')
            ->assertJsonPath('data.scope', 'international')
            ->assertJsonPath('data.origin.name', 'Universidad Local')
            ->assertJsonPath('data.destination.country', 'FR');

        $this->assertDatabaseHas('academic_mobilities', ['participant_id' => '1', 'strategy' => 'exchange']);
    }

    public function test_store_validates_the_payload(): void
    {
        $this->registerStudents();
        $this->actingAs($this->userWith(MobilityAbility::Create));

        $this->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['participant_type', 'participant_id', 'strategy', 'scope', 'institution_id']);
        $this->postJson(self::URL, $this->payload(['participant_type' => 'alien']))->assertJsonValidationErrors('participant_type');
        // A real kind, but the host has not registered a resolver for it.
        $this->postJson(self::URL, $this->payload(['participant_type' => 'professor']))->assertJsonValidationErrors('participant_type');
        $this->postJson(self::URL, $this->payload(['strategy' => 'nope']))->assertJsonValidationErrors('strategy');
    }

    public function test_unknown_participant_is_a_validation_error(): void
    {
        $this->registerStudents();

        $this->actingAs($this->userWith(MobilityAbility::Create))
            ->postJson(self::URL, $this->payload(['participant_id' => '99']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participant_id');
    }

    public function test_the_direction_puts_the_local_institution_on_the_right_side(): void
    {
        $this->registerStudents();
        $this->actingAs($this->userWith(MobilityAbility::Create));

        $this->postJson(self::URL, $this->payload(['direction' => 'incoming', 'scope' => 'national', 'institution_id' => $this->national->id]))
            ->assertCreated()
            ->assertJsonPath('data.direction', 'incoming')
            ->assertJsonPath('data.scope', 'national')
            ->assertJsonPath('data.origin.name', 'Universidad de Barcelona')
            ->assertJsonPath('data.destination.name', 'Universidad Local');
    }

    public function test_the_scope_must_agree_with_the_institution(): void
    {
        $this->registerStudents();
        $this->actingAs($this->userWith(MobilityAbility::Create));

        // Sorbonne is abroad, Barcelona is in the local country.
        $this->postJson(self::URL, $this->payload(['scope' => 'national', 'institution_id' => $this->foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->postJson(self::URL, $this->payload(['scope' => 'international', 'institution_id' => $this->national->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->postJson(self::URL, $this->payload(['scope' => 'galactic']))
            ->assertUnprocessable()->assertJsonValidationErrors('scope');

        $this->assertDatabaseCount('academic_mobilities', 0);
    }

    public function test_the_strategy_can_fix_the_direction(): void
    {
        $this->registerStudents();
        $this->actingAs($this->userWith(MobilityAbility::Create));

        $this->postJson(self::URL, $this->payload(['strategy' => 'outgoing_exchange', 'direction' => null]))
            ->assertCreated()
            ->assertJsonPath('data.direction', 'outgoing');

        $this->postJson(self::URL, $this->payload(['strategy' => 'outgoing_exchange', 'direction' => 'incoming']))
            ->assertUnprocessable()->assertJsonValidationErrors('direction');

        // A strategy for both directions needs one.
        $this->postJson(self::URL, $this->payload(['strategy' => 'exchange', 'direction' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('direction');
    }

    public function test_institution_errors_are_validation_errors(): void
    {
        $this->registerStudents();
        $this->actingAs($this->userWith(MobilityAbility::Create));

        $this->postJson(self::URL, $this->payload(['institution_id' => $this->local->id, 'scope' => 'national']))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->postJson(self::URL, $this->payload(['institution_id' => '01JMISSINGMISSINGMISSING00']))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->postJson(self::URL, $this->payload(['direction' => 'sideways']))
            ->assertUnprocessable()->assertJsonValidationErrors('direction');
    }

    public function test_filters_by_direction_and_scope(): void
    {
        $this->mobility(['direction' => 'outgoing', 'scope' => 'international']);
        $this->mobility(['direction' => 'incoming', 'scope' => 'national']);
        $this->mobility(['direction' => 'incoming', 'scope' => 'international']);

        $this->actingAs($this->userWith(MobilityAbility::ViewAny));

        $this->getJson(self::URL.'?direction=incoming')->assertJsonPath('total', 2);
        $this->getJson(self::URL.'?direction=incoming&scope=national')->assertJsonPath('total', 1);
        $this->getJson(self::URL.'?scope=sideways')->assertUnprocessable();
    }

    public function test_ineligible_participants_get_every_failed_rule(): void
    {
        $this->registerStudents();

        $this->actingAs($this->userWith(MobilityAbility::Create))
            ->postJson(self::URL, $this->payload(['participant_id' => '2', 'strategy' => 'exchange', 'metadata' => ['destination' => 'abroad']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eligibility');

        $this->assertDatabaseCount('academic_mobilities', 0);
    }

    public function test_options_list_the_registered_aliases(): void
    {
        $this->registerStudents();

        $this->actingAs($this->userWith(MobilityAbility::Create))
            ->getJson(self::URL.'/options')
            ->assertOk()
            ->assertJsonPath('data.participant_types', ['outgoing' => ['student'], 'incoming' => ['student']])
            ->assertJsonPath('data.default_strategy', 'default')
            ->assertJsonPath('data.strategies', [
                ['alias' => 'default', 'direction' => null],
                ['alias' => 'exchange', 'direction' => null],
                ['alias' => 'outgoing_exchange', 'direction' => 'outgoing'],
            ])
            ->assertJsonPath('data.institutions.0.name', 'Universidad Local')
            ->assertJsonPath('data.institutions.0.is_local', true)
            ->assertJsonCount(3, 'data.institutions');

        $this->actingAs($this->userWith(MobilityAbility::ViewAny))->getJson(self::URL.'/options')->assertForbidden();
    }

    public function test_searches_participants_of_a_type(): void
    {
        $this->registerStudents();

        $this->actingAs($this->userWith(MobilityAbility::Create));

        $this->getJson(self::URL.'/participants?type=student&direction=outgoing&search=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', [
                'type' => 'student', 'id' => '2', 'name' => 'Participant 2',
                'details' => [['label' => 'Enrollment', 'value' => 'inactive']],
            ]);

        $this->getJson(self::URL.'/participants?type=student&direction=outgoing')->assertJsonCount(2, 'data');
        $this->getJson(self::URL.'/participants?type=alien&direction=outgoing')->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->getJson(self::URL.'/participants')->assertUnprocessable();
    }

    public function test_searching_participants_needs_the_create_ability(): void
    {
        $this->registerStudents();

        $this->actingAs($this->userWith(MobilityAbility::ViewAny))
            ->getJson(self::URL.'/participants?type=student&direction=outgoing')
            ->assertForbidden();
    }

    public function test_options_preselect_the_signed_in_participant_when_the_host_maps_it(): void
    {
        $this->registerStudents();
        $this->actingAs($this->userWith(MobilityAbility::Create));

        $this->getJson(self::URL.'/options')->assertJsonPath('data.current_participants', []);

        AcademicMobility::resolveCurrentParticipantUsing(fn ($user) => new ParticipantReference('student', (string) $user->getKey()));

        $this->getJson(self::URL.'/options')
            ->assertJsonPath('data.current_participants', [[
                'type' => 'student', 'id' => '1', 'name' => 'Participant 1',
                'details' => [['label' => 'Enrollment', 'value' => 'active']],
            ]]);

        // A user that is not a known participant gets none.
        AcademicMobility::resolveCurrentParticipantUsing(fn () => new ParticipantReference('student', '99'));
        $this->getJson(self::URL.'/options')->assertJsonPath('data.current_participants', []);

        // Several profiles: only the ones with a resolver and a person are listed.
        AcademicMobility::resolveCurrentParticipantUsing(fn () => [
            new ParticipantReference('student', '2'),
            new ParticipantReference('professor', '1'),
            new ParticipantReference('student', '99'),
        ]);
        $this->getJson(self::URL.'/options')->assertJsonCount(1, 'data.current_participants')->assertJsonPath('data.current_participants.0.id', '2');
    }

    public function test_visitors_are_searched_in_their_own_source_by_origin_institution(): void
    {
        $this->registerStudents();
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([
            'V1' => ['institution_id' => $this->foreign->id, 'enrollment' => 'active'],
            'V2' => ['institution_id' => $this->national->id, 'enrollment' => 'active'],
        ]), MobilityDirection::Incoming);

        $this->actingAs($this->userWith(MobilityAbility::Create));

        // Own people leave, visitors arrive, each from their own source.
        $this->getJson(self::URL.'/participants?type=student&direction=outgoing')->assertJsonCount(2, 'data');
        $this->getJson(self::URL.'/participants?type=student&direction=incoming&institution_id='.$this->foreign->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'V1');

        // Visitors are always looked up by the origin they come from.
        $this->getJson(self::URL.'/participants?type=student&direction=incoming')
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');

        // There is no source of professors for either direction.
        $this->getJson(self::URL.'/participants?type=professor&direction=outgoing')->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->getJson(self::URL.'/participants?type=student&direction=sideways')->assertUnprocessable()->assertJsonValidationErrors('direction');
    }

    public function test_a_mobility_for_a_visitor_arrives_from_their_institution(): void
    {
        $this->registerStudents();
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([
            'V1' => ['institution_id' => $this->foreign->id, 'enrollment' => 'active'],
        ]), MobilityDirection::Incoming);

        $this->actingAs($this->userWith(MobilityAbility::Create));

        $this->postJson(self::URL, $this->payload([
            'participant_id' => 'V1', 'direction' => 'incoming', 'scope' => 'international', 'institution_id' => $this->foreign->id,
        ]))->assertCreated()->assertJsonPath('data.origin.name', 'Sorbonne')->assertJsonPath('data.destination.name', 'Universidad Local');

        // A visitor of Sorbonne cannot arrive from Barcelona.
        $this->postJson(self::URL, $this->payload([
            'participant_id' => 'V1', 'direction' => 'incoming', 'scope' => 'national', 'institution_id' => $this->national->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('participant_id');

        // The kind has no source for that direction.
        $this->postJson(self::URL, $this->payload(['participant_type' => 'professor', 'direction' => 'incoming']))
            ->assertUnprocessable()->assertJsonValidationErrors('participant_type');
    }

    public function test_searches_partner_institutions_of_a_scope(): void
    {
        Institution::create(['name' => 'Universidad de Los Andes', 'country' => 'ES']);
        $this->actingAs($this->userWith(MobilityAbility::Create));

        $names = fn (string $query) => collect($this->getJson(self::URL.'/institutions?'.$query)->assertOk()->json('data'))->pluck('name')->all();

        // Never the local institution; the scope is relative to its country (ES).
        $this->assertSame(['Sorbonne'], $names('scope=international'));
        $this->assertSame(['Universidad de Barcelona', 'Universidad de Los Andes'], $names('scope=national'));
        $this->assertSame(['Sorbonne', 'Universidad de Barcelona', 'Universidad de Los Andes'], $names(''));

        // Words in any order, ignoring case, in the name or the country code.
        $this->assertSame(['Universidad de Los Andes'], $names('search=los+UNIVERSIDAD'));
        $this->assertSame(['Sorbonne'], $names('search=fr'));
        $this->assertSame([], $names('search=zzz'));
        $this->assertSame([], $names('scope=national&search=sorbonne'));

        $this->getJson(self::URL.'/institutions?scope=galactic')->assertUnprocessable()->assertJsonValidationErrors('scope');
    }

    public function test_searching_institutions_needs_the_create_ability(): void
    {
        $this->actingAs($this->userWith(MobilityAbility::ViewAny))->getJson(self::URL.'/institutions')->assertForbidden();
    }

    public function test_a_visitor_that_does_not_exist_can_be_registered_from_the_form(): void
    {
        $this->registerStudents();
        $visitors = new CreatableArrayResolver;
        AcademicMobility::registerParticipantResolver('student', $visitors, MobilityDirection::Incoming);

        $this->actingAs($this->userWith(MobilityAbility::Create));

        // The form is described by the host's resolver, per direction and kind.
        $this->getJson(self::URL.'/options')
            ->assertJsonPath('data.participant_forms.incoming.student.fields.0.name', 'name')
            ->assertJsonPath('data.participant_forms.incoming.student.fields.1.label', 'Email')
            ->assertJsonPath('data.participant_forms.incoming.student.can_sample', false)
            ->assertJsonMissingPath('data.participant_forms.outgoing');

        $url = self::URL.'/participants';
        $payload = ['type' => 'student', 'direction' => 'incoming', 'institution_id' => $this->foreign->id, 'data' => ['name' => 'Camille', 'email' => 'c@x.org']];

        $created = $this->postJson($url, $payload)->assertCreated()->assertJsonPath('data.name', 'Participant V1')->json('data');

        // The new person is at once a valid participant of a mobility arriving from their institution.
        $this->postJson(self::URL, $this->payload([
            'participant_id' => $created['id'], 'direction' => 'incoming', 'scope' => 'international', 'institution_id' => $this->foreign->id,
        ]))->assertCreated()->assertJsonPath('data.origin.name', 'Sorbonne');

        // The host's rules apply (here: no repeated email).
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('data.email');
        $this->postJson($url, ['data' => ['email' => 'nope']] + $payload)->assertUnprocessable()->assertJsonValidationErrors(['data.name', 'data.email']);
    }

    public function test_only_sources_that_allow_it_register_people(): void
    {
        $this->registerStudents();
        $this->actingAs($this->userWith(MobilityAbility::Create));

        // Own people (outgoing) come from the host's own data; this source cannot create anyone.
        $this->postJson(self::URL.'/participants', ['type' => 'student', 'direction' => 'outgoing', 'data' => ['name' => 'X']])
            ->assertUnprocessable()->assertJsonValidationErrors('type');

        AcademicMobility::registerParticipantResolver('student', new CreatableArrayResolver, MobilityDirection::Incoming);

        $this->postJson(self::URL.'/participants', ['type' => 'student', 'direction' => 'incoming', 'data' => ['name' => 'X']])
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->postJson(self::URL.'/participants', ['type' => 'student', 'direction' => 'incoming', 'institution_id' => '01JMISSINGMISSINGMISSING00', 'data' => ['name' => 'X']])
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');

        $this->actingAs($this->userWith(MobilityAbility::ViewAny))
            ->postJson(self::URL.'/participants', ['type' => 'student', 'direction' => 'incoming', 'institution_id' => $this->foreign->id, 'data' => ['name' => 'X']])
            ->assertForbidden();
    }

    public function test_random_samples_are_only_offered_when_enabled(): void
    {
        $this->registerStudents();
        AcademicMobility::registerParticipantResolver('student', new CreatableArrayResolver, MobilityDirection::Incoming);
        $this->actingAs($this->userWith(MobilityAbility::Create));

        $url = self::URL.'/participants/sample?type=student&direction=incoming&institution_id='.$this->foreign->id;

        // Off by default: neither the button nor the endpoint.
        $this->getJson(self::URL.'/options')->assertJsonPath('data.participant_forms.incoming.student.can_sample', false);
        $this->getJson($url)->assertForbidden();

        config(['academic-mobility.samples' => true]);

        $this->getJson(self::URL.'/options')->assertJsonPath('data.participant_forms.incoming.student.can_sample', true);
        $sample = $this->getJson($url)->assertOk()->json('data');

        // What the host invents is valid for what the host asks.
        $this->postJson(self::URL.'/participants', ['type' => 'student', 'direction' => 'incoming', 'institution_id' => $this->foreign->id, 'data' => $sample])
            ->assertCreated();

        $this->getJson(self::URL.'/participants/sample?type=student&direction=outgoing')->assertUnprocessable();
        $this->getJson(self::URL.'/participants/sample?type=student&direction=incoming')->assertUnprocessable()->assertJsonValidationErrors('institution_id');
    }
}
