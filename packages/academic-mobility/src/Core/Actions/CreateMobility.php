<?php

namespace Module\AcademicMobility\Core\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Module\AcademicMobility\Contracts\AffiliatedParticipant;
use Module\AcademicMobility\Contracts\DirectionalStrategy;
use Module\AcademicMobility\Contracts\MobilityStrategy;
use Module\AcademicMobility\Core\DTOs\CreateMobilityData;
use Illuminate\Support\Facades\DB;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Events\MobilityCreated;
use Module\AcademicMobility\Core\Exceptions\AgreementNotApplicable;
use Module\AcademicMobility\Core\Exceptions\InstitutionNotFound;
use Module\AcademicMobility\Core\Exceptions\InvalidRoute;
use Module\AcademicMobility\Core\Exceptions\MobilityNotEligible;
use Module\AcademicMobility\Core\Exceptions\ParticipantNotFound;
use Module\AcademicMobility\Core\Models\Agreement;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\Models\Requirement;
use Module\AcademicMobility\Core\Persistence\MobilityRepository;
use Module\AcademicMobility\Core\Support\RouteResolver;
use Module\AcademicMobility\Core\ValueObjects\MobilityRoute;
use Module\AcademicMobility\Rules\RuleContext;
use Module\AcademicMobility\Rules\RuleEngine;
use Module\AcademicMobility\Support\ExtensionKind;
use Module\AcademicMobility\Support\ExtensionRegistry;

/**
 * Creates a draft mobility after the participant (found by the host's
 * resolver) passes the rules of the chosen strategy.
 */
class CreateMobility
{
    public function __construct(
        private readonly ExtensionRegistry $extensions,
        private readonly RuleEngine $rules,
        private readonly RouteResolver $routes,
        private readonly MobilityRepository $repository,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws ParticipantNotFound
     * @throws InstitutionNotFound
     * @throws InvalidRoute
     * @throws MobilityNotEligible
     */
    public function handle(CreateMobilityData $data): Mobility
    {
        $alias = $data->strategy ?? config('academic-mobility.default_strategy', 'default');

        /** @var MobilityStrategy $strategy */
        $strategy = $this->extensions->resolve(ExtensionKind::Strategy, $alias);

        $route = $this->routes->resolve($this->direction($strategy, $alias, $data->direction), $data->scope, $data->institutionId);

        // The direction says where the participant comes from: own people leaving, visitors arriving.
        $participant = $this->extensions->participantResolver($route->direction, $data->participant->type)->resolve($data->participant->id)
            ?? throw ParticipantNotFound::make($data->participant);

        // A visitor with a known institution has to come from the chosen origin.
        if ($participant instanceof AffiliatedParticipant && $route->direction === MobilityDirection::Incoming
            && $participant->institutionId() !== null && $participant->institutionId() !== $route->origin->getKey()) {
            throw InvalidRoute::participantFromAnotherInstitution();
        }

        $agreement = $data->agreementId === null ? null : $this->agreement($data->agreementId, $route);

        $failures = $this->rules->failures($strategy->rules(), new RuleContext($participant, $data->metadata, $route));

        if ($failures !== []) {
            throw new MobilityNotEligible($failures);
        }

        $mobility = DB::connection(config('academic-mobility.connection'))->transaction(function () use ($data, $alias, $route, $agreement) {
            $mobility = $this->repository->create([
            'participant_type' => $data->participant->type,
            'participant_id' => $data->participant->id,
            'strategy' => $alias,
            'direction' => $route->direction,
            'scope' => $route->scope,
            'origin_institution_id' => $route->origin->getKey(),
            'destination_institution_id' => $route->destination->getKey(),
            'agreement_id' => $agreement?->getKey(),
            'status' => MobilityStatus::Draft,
            'metadata' => $data->metadata,
            ]);

            $this->snapshotRequirements($mobility);

            return $mobility;
        });

        $this->events->dispatch(new MobilityCreated($mobility));

        return $mobility;
    }

    /**
     * The agreement the mobility is made under: one in force that binds the institution on the other side
     * (its own agreements, or a multilateral one it takes part in).
     *
     * @throws AgreementNotApplicable
     */
    private function agreement(string $id, MobilityRoute $route): Agreement
    {
        $partner = $route->direction === MobilityDirection::Incoming ? $route->origin : $route->destination;
        $model = config('academic-mobility.models.agreement', Agreement::class);

        $agreement = $model::query()->find($id) ?? throw AgreementNotApplicable::notFound();

        if (! $model::query()->whereKey($id)->forInstitution($partner->getKey())->exists()) {
            throw AgreementNotApplicable::notOfTheInstitution();
        }

        return $agreement->isInForce() ? $agreement : throw AgreementNotApplicable::notInForce();
    }

    /**
     * The strategy's own direction if it has one, else the requested one.
     *
     * @throws InvalidRoute
     */
    private function direction(MobilityStrategy $strategy, string $alias, ?MobilityDirection $requested): MobilityDirection
    {
        $fixed = $strategy instanceof DirectionalStrategy ? $strategy->direction() : null;

        if ($fixed !== null && $requested !== null && $requested !== $fixed) {
            throw InvalidRoute::directionNotAllowed($alias, $fixed->value);
        }

        return $fixed ?? $requested ?? throw InvalidRoute::directionRequired($alias);
    }

    /**
     * Copies the requirements that apply to this kind of mobility (direction, scope and participant kind),
     * so the request keeps its own list even if the catalog changes afterwards.
     */
    private function snapshotRequirements(Mobility $mobility): void
    {
        $kind = ParticipantKind::tryFrom($mobility->participant_type);

        if ($kind === null) {
            return;
        }

        config('academic-mobility.models.requirement', Requirement::class)::query()
            ->applicableTo($mobility->direction, $mobility->scope, $kind)
            ->ordered()
            ->get()
            ->each(fn (Requirement $requirement) => $mobility->requirements()->create([
                'requirement_id' => $requirement->id,
                'name' => $requirement->name,
                'description' => $requirement->description,
                'is_mandatory' => $requirement->is_mandatory,
                'position' => $requirement->position,
                'translations' => $requirement->translations,
            ]));
    }
}
