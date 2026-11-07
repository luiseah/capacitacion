<?php

namespace Module\AcademicMobility\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Module\AcademicMobility\Core\DTOs\CreateMobilityData;
use Module\AcademicMobility\Contracts\MobilityStrategy;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Events\MobilityCreated;
use Module\AcademicMobility\Core\Exceptions\InstitutionNotFound;
use Module\AcademicMobility\Core\Exceptions\InvalidRoute;
use Module\AcademicMobility\Core\Exceptions\InvalidTransition;
use Module\AcademicMobility\Core\Exceptions\MobilityNotEligible;
use Module\AcademicMobility\Core\Exceptions\ParticipantNotFound;
use Module\AcademicMobility\Core\Exceptions\UnknownExtension;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Tests\Support\AbroadCondition;
use Module\AcademicMobility\Tests\Support\ActiveEnrollmentRule;
use Module\AcademicMobility\Tests\Support\ArrayParticipantResolver;
use Module\AcademicMobility\Tests\Support\CreatesInstitutions;
use Module\AcademicMobility\Tests\Support\ExchangeStrategy;
use Module\AcademicMobility\Tests\Support\OutgoingExchangeStrategy;
use Module\AcademicMobility\Tests\TestCase;
use Module\AcademicMobility\Workflow\Events\MobilityStatusChanged;

class MobilityLifecycleTest extends TestCase
{
    use CreatesInstitutions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstitutions();

        // What a host app does in its own service provider.
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([
            1 => ['enrollment' => 'active', 'gpa' => 4.5],
            2 => ['enrollment' => 'inactive', 'gpa' => 4.5],
            3 => ['enrollment' => 'active', 'gpa' => 3.0],
        ]));
        AcademicMobility::registerRule('active_enrollment', ActiveEnrollmentRule::class);
        AcademicMobility::registerConditionEvaluator('abroad', AbroadCondition::class);
        AcademicMobility::registerStrategy('exchange', ExchangeStrategy::class);
    }

    private function data(int $student, ?string $strategy = 'exchange', array $metadata = []): CreateMobilityData
    {
        return new CreateMobilityData(
            new ParticipantReference('student', $student),
            MobilityDirection::Outgoing,
            MobilityScope::International,
            $this->foreign->id,
            $strategy,
            $metadata,
        );
    }

    public function test_creates_a_draft_for_an_eligible_participant_and_fires_the_event(): void
    {
        Event::fake([MobilityCreated::class]);

        $mobility = AcademicMobility::create($this->data(1, metadata: ['destination' => 'abroad']));

        $this->assertSame(MobilityStatus::Draft, $mobility->status);
        $this->assertSame('exchange', $mobility->strategy);
        $this->assertSame(['destination' => 'abroad'], $mobility->metadata);
        $this->assertTrue($mobility->participant()->equals(new ParticipantReference('student', 1)));
        $this->assertDatabaseHas('academic_mobilities', ['id' => $mobility->id, 'participant_id' => '1']);
        Event::assertDispatched(MobilityCreated::class, fn (MobilityCreated $e) => $e->mobility->is($mobility));
    }

    public function test_uses_the_default_strategy_when_none_is_given(): void
    {
        $this->assertSame('default', AcademicMobility::create($this->data(2, strategy: null))->strategy);
    }

    public function test_rejects_ineligible_participants_with_every_failure(): void
    {
        try {
            AcademicMobility::create($this->data(2, metadata: ['destination' => 'abroad']));
            $this->fail('Student 2 is not enrolled.');
        } catch (MobilityNotEligible $e) {
            $this->assertCount(1, $e->failures);
            $this->assertSame('active_enrollment', $e->failures[0]->rule);
        }

        $this->assertDatabaseCount('academic_mobilities', 0);
    }

    public function test_the_condition_decides_whether_a_rule_applies(): void
    {
        // Low GPA only matters abroad.
        $this->assertNotNull(AcademicMobility::create($this->data(3, metadata: ['destination' => 'local'])));

        try {
            AcademicMobility::create($this->data(3, metadata: ['destination' => 'abroad']));
            $this->fail('GPA is below the minimum for mobilities abroad.');
        } catch (MobilityNotEligible $e) {
            $this->assertSame('GPA must be at least 4.0.', $e->failures[0]->message);
        }
    }

    public function test_fails_clearly_for_unknown_participants_types_and_strategies(): void
    {
        $this->expectException(ParticipantNotFound::class);
        AcademicMobility::create($this->data(99));
    }

    public function test_unknown_participant_type_and_strategy(): void
    {
        try {
            AcademicMobility::create(new CreateMobilityData(new ParticipantReference('professor', 1), MobilityDirection::Outgoing, MobilityScope::International, $this->foreign->id));
            $this->fail('There is no professor resolver.');
        } catch (UnknownExtension) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(UnknownExtension::class);
        AcademicMobility::create($this->data(1, strategy: 'missing'));
    }

    public function test_a_host_can_replace_a_builtin_strategy(): void
    {
        AcademicMobility::registerStrategy('default', ExchangeStrategy::class);

        $this->expectException(MobilityNotEligible::class);
        AcademicMobility::create($this->data(2, strategy: null));
    }

    public function test_transitions_follow_the_workflow_and_fire_events(): void
    {
        Event::fake([MobilityStatusChanged::class]);
        $mobility = AcademicMobility::create($this->data(1, metadata: ['destination' => 'local']));

        AcademicMobility::transition($mobility, MobilityStatus::Submitted);

        $this->assertSame(MobilityStatus::Submitted, $mobility->fresh()->status);
        Event::assertDispatched(
            MobilityStatusChanged::class,
            fn (MobilityStatusChanged $e) => $e->from === MobilityStatus::Draft && $e->to === MobilityStatus::Submitted,
        );
    }

    public function test_rejects_invalid_transitions(): void
    {
        $mobility = AcademicMobility::create($this->data(1, metadata: ['destination' => 'local']));

        $this->expectException(InvalidTransition::class);
        AcademicMobility::transition($mobility, MobilityStatus::Approved);
    }

    public function test_derives_direction_and_scope_from_the_institutions(): void
    {
        $outgoingAbroad = AcademicMobility::create($this->data(1));
        $this->assertSame(MobilityDirection::Outgoing, $outgoingAbroad->direction);
        $this->assertSame(MobilityScope::International, $outgoingAbroad->scope);
        $this->assertSame($this->local->id, $outgoingAbroad->origin_institution_id);
        $this->assertSame($this->foreign->id, $outgoingAbroad->destination_institution_id);

        $incomingNational = AcademicMobility::create(new CreateMobilityData(
            new ParticipantReference('student', 1), MobilityDirection::Incoming, MobilityScope::National, $this->national->id, 'exchange',
        ));
        $this->assertSame($this->national->id, $incomingNational->origin_institution_id);
        $this->assertSame($this->local->id, $incomingNational->destination_institution_id);
        $this->assertSame(MobilityDirection::Incoming, $incomingNational->direction);
        $this->assertSame(MobilityScope::National, $incomingNational->scope);
    }

    public function test_the_local_institution_is_always_one_end(): void
    {
        $create = fn (MobilityDirection $direction, MobilityScope $scope, string $institution) => fn () => AcademicMobility::create(
            new CreateMobilityData(new ParticipantReference('student', 1), $direction, $scope, $institution, 'exchange'),
        );

        // The other end cannot be the local institution itself, an unknown one, nor disagree with the scope.
        $this->assertThrows($create(MobilityDirection::Outgoing, MobilityScope::National, $this->local->id), InvalidRoute::class);
        $this->assertThrows($create(MobilityDirection::Outgoing, MobilityScope::National, $this->foreign->id), InvalidRoute::class);
        $this->assertThrows($create(MobilityDirection::Outgoing, MobilityScope::International, $this->national->id), InvalidRoute::class);
        $this->assertThrows($create(MobilityDirection::Outgoing, MobilityScope::International, 'missing'), InstitutionNotFound::class);
        $this->assertDatabaseCount('academic_mobilities', 0);

        // The direction decides on which side the local institution goes.
        $outgoing = $create(MobilityDirection::Outgoing, MobilityScope::International, $this->foreign->id)();
        $incoming = $create(MobilityDirection::Incoming, MobilityScope::International, $this->foreign->id)();

        $this->assertSame([$this->local->id, $this->foreign->id], [$outgoing->origin_institution_id, $outgoing->destination_institution_id]);
        $this->assertSame([$this->foreign->id, $this->local->id], [$incoming->origin_institution_id, $incoming->destination_institution_id]);
    }

    public function test_needs_a_local_institution(): void
    {
        Institution::query()->update(['is_local' => false]);

        $this->expectException(InvalidRoute::class);
        AcademicMobility::create($this->data(1));
    }

    public function test_built_in_conditions_look_at_the_route(): void
    {
        AcademicMobility::registerStrategy('abroad_only', new class implements MobilityStrategy
        {
            public function rules(): array
            {
                return [[
                    'rule' => 'participant_attribute',
                    'params' => ['attribute' => 'gpa', 'operator' => '>=', 'value' => 4.0],
                    'message' => 'GPA must be at least 4.0 for outgoing international mobilities.',
                    'when' => ['evaluator' => 'scope', 'params' => ['is' => 'international']],
                ], [
                    'rule' => 'participant_attribute',
                    'params' => ['attribute' => 'enrollment', 'operator' => '=', 'value' => 'active'],
                    'message' => 'Only enrolled students go out.',
                    'when' => ['evaluator' => 'direction', 'params' => ['is' => ['outgoing']]],
                ]];
            }

            public function documentProviders(): array
            {
                return [];
            }

            public function approvers(): array
            {
                return [];
            }
        });

        // Student 3 (gpa 3.0) is fine nationally...
        $this->assertNotNull(AcademicMobility::create(new CreateMobilityData(
            new ParticipantReference('student', 3), MobilityDirection::Outgoing, MobilityScope::National, $this->national->id, 'abroad_only',
        )));

        // ...but not abroad.
        try {
            AcademicMobility::create(new CreateMobilityData(
                new ParticipantReference('student', 3), MobilityDirection::Outgoing, MobilityScope::International, $this->foreign->id, 'abroad_only',
            ));
            $this->fail('GPA is too low for an international mobility.');
        } catch (MobilityNotEligible $e) {
            $this->assertSame('GPA must be at least 4.0 for outgoing international mobilities.', $e->failures[0]->message);
        }
    }

    public function test_a_directional_strategy_fixes_the_direction(): void
    {
        AcademicMobility::registerStrategy('outgoing_exchange', OutgoingExchangeStrategy::class);
        $participant = new ParticipantReference('student', 1);

        // No direction needed: the strategy says outgoing.
        $mobility = AcademicMobility::create(new CreateMobilityData($participant, null, MobilityScope::International, $this->foreign->id, 'outgoing_exchange'));
        $this->assertSame(MobilityDirection::Outgoing, $mobility->direction);
        $this->assertSame($this->local->id, $mobility->origin_institution_id);

        // Saying the same is fine, saying the opposite is not.
        $this->assertNotNull(AcademicMobility::create(new CreateMobilityData($participant, MobilityDirection::Outgoing, MobilityScope::International, $this->foreign->id, 'outgoing_exchange')));
        $this->assertThrows(
            fn () => AcademicMobility::create(new CreateMobilityData($participant, MobilityDirection::Incoming, MobilityScope::International, $this->foreign->id, 'outgoing_exchange')),
            InvalidRoute::class,
        );
    }

    public function test_a_strategy_for_both_directions_needs_one(): void
    {
        $this->assertThrows(
            fn () => AcademicMobility::create(new CreateMobilityData(new ParticipantReference('student', 1), null, MobilityScope::International, $this->foreign->id, 'exchange')),
            InvalidRoute::class,
        );
        $this->assertDatabaseCount('academic_mobilities', 0);
    }

    private function visitorsOf(string $institutionId): ArrayParticipantResolver
    {
        return new ArrayParticipantResolver(['V1' => ['enrollment' => 'active', 'gpa' => 4.5, 'institution_id' => $institutionId]]);
    }

    public function test_the_direction_decides_where_the_participant_comes_from(): void
    {
        // Own students leave, visitors arrive: two different sources for the same kind.
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([1 => ['enrollment' => 'active', 'gpa' => 4.5]]), MobilityDirection::Outgoing);
        AcademicMobility::registerParticipantResolver('student', $this->visitorsOf($this->foreign->id), MobilityDirection::Incoming);

        $create = fn (MobilityDirection $direction, string|int $id) => fn () => AcademicMobility::create(new CreateMobilityData(
            new ParticipantReference('student', $id), $direction, MobilityScope::International, $this->foreign->id, 'exchange',
        ));

        $this->assertSame('1', $create(MobilityDirection::Outgoing, 1)()->participant_id);
        $this->assertSame('V1', $create(MobilityDirection::Incoming, 'V1')()->participant_id);

        // A visitor is not one of our students, nor the other way round.
        $this->assertThrows($create(MobilityDirection::Outgoing, 'V1'), ParticipantNotFound::class);
        $this->assertThrows($create(MobilityDirection::Incoming, 1), ParticipantNotFound::class);
    }

    public function test_a_visitor_must_come_from_the_origin_institution(): void
    {
        AcademicMobility::registerParticipantResolver('student', $this->visitorsOf($this->foreign->id), MobilityDirection::Incoming);

        // V1 belongs to Sorbonne, so a mobility arriving from Barcelona is not theirs.
        $this->assertThrows(
            fn () => AcademicMobility::create(new CreateMobilityData(
                new ParticipantReference('student', 'V1'), MobilityDirection::Incoming, MobilityScope::National, $this->national->id, 'exchange',
            )),
            InvalidRoute::class,
        );
        $this->assertDatabaseCount('academic_mobilities', 0);

        $this->assertNotNull(AcademicMobility::create(new CreateMobilityData(
            new ParticipantReference('student', 'V1'), MobilityDirection::Incoming, MobilityScope::International, $this->foreign->id, 'exchange',
        )));
    }

    public function test_a_direction_without_a_source_of_that_kind_fails(): void
    {
        AcademicMobility::registerParticipantResolver('professor', new ArrayParticipantResolver([1 => []], 'professor'), MobilityDirection::Outgoing);

        $this->assertSame(['student', 'professor'], AcademicMobility::participantKinds(MobilityDirection::Outgoing));
        $this->assertSame(['student'], AcademicMobility::participantKinds(MobilityDirection::Incoming));

        $this->expectException(UnknownExtension::class);
        AcademicMobility::create(new CreateMobilityData(
            new ParticipantReference('professor', 1), MobilityDirection::Incoming, MobilityScope::International, $this->foreign->id, 'exchange',
        ));
    }
}
