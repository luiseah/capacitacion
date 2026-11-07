<?php

namespace Module\AcademicMobility\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Module\AcademicMobility\Contracts\CreatableParticipantResolver;
use Module\AcademicMobility\Contracts\DetailedParticipant;
use Module\AcademicMobility\Contracts\DirectionalStrategy;
use Module\AcademicMobility\Contracts\Participant;
use Module\AcademicMobility\Contracts\SampleParticipantResolver;
use Module\AcademicMobility\Contracts\SearchableParticipantResolver;
use Module\AcademicMobility\Core\DTOs\CreateMobilityData;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Exceptions\AgreementNotApplicable;
use Module\AcademicMobility\Core\Exceptions\InstitutionNotFound;
use Module\AcademicMobility\Core\Exceptions\InvalidRoute;
use Module\AcademicMobility\Core\Exceptions\MobilityNotEligible;
use Module\AcademicMobility\Core\Exceptions\ParticipantNotFound;
use Module\AcademicMobility\Core\Exceptions\UnknownExtension;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Http\Requests\IndexMobilitiesRequest;
use Module\AcademicMobility\Http\Requests\PreviewRequirementsRequest;
use Module\AcademicMobility\Http\Requests\SampleParticipantRequest;
use Module\AcademicMobility\Http\Requests\SearchInstitutionsRequest;
use Module\AcademicMobility\Http\Requests\SearchParticipantsRequest;
use Module\AcademicMobility\Http\Requests\StoreMobilityRequest;
use Module\AcademicMobility\Http\Requests\StoreParticipantRequest;
use Module\AcademicMobility\Http\Resources\InstitutionResource;
use Module\AcademicMobility\Http\Resources\MobilityResource;
use Module\AcademicMobility\Http\Resources\RequirementResource;
use Module\AcademicMobility\Rules\RuleResult;
use Module\AcademicMobility\Support\ExtensionKind;

class MobilityController extends Controller
{
    public function index(IndexMobilitiesRequest $request): JsonResponse
    {
        $model = config('academic-mobility.models.mobility');

        $mobilities = $model::query()
            ->applyFilters($request)
            ->paginated((int) config('academic-mobility.per_page', 15))
            ->through(fn ($mobility) => (new MobilityResource($mobility))->resolve($request));

        return Response::api($mobilities, __('Successful'));
    }

    /**
     * What the creation form can offer: the aliases the host registered.
     */
    public function options(Request $request): JsonResponse
    {
        Gate::authorize('create', config('academic-mobility.models.mobility'));

        return Response::api([
            // Where the participants come from depends on the direction: own people leave, visitors arrive.
            'participant_types' => [
                MobilityDirection::Outgoing->value => AcademicMobility::participantKinds(MobilityDirection::Outgoing),
                MobilityDirection::Incoming->value => AcademicMobility::participantKinds(MobilityDirection::Incoming),
            ],
            // The registration form of the people that can be created from here (visitors), per direction and kind.
            'participant_forms' => $this->participantForms(),
            'strategies' => array_map(
                fn (string $alias) => [
                    'alias' => $alias,
                    // Fixed by the strategy, or null when the form has to ask for it.
                    'direction' => ($strategy = AcademicMobility::extension(ExtensionKind::Strategy, $alias)) instanceof DirectionalStrategy
                        ? $strategy->direction()->value
                        : null,
                ],
                AcademicMobility::aliases(ExtensionKind::Strategy),
            ),
            // The signed-in user as one of the university's own people, when the host says how to map them.
            'current_participants' => $this->currentParticipants($request),
            'default_strategy' => config('academic-mobility.default_strategy', 'default'),
            'institutions' => InstitutionResource::collection(config('academic-mobility.models.institution')::query()->orderByDesc('is_local')->orderBy('name')->get())->resolve(),
        ]);
    }

    /**
     * What a mobility with this direction, scope and participant kind has to bring (the creation form's preview).
     */
    public function requirementsPreview(PreviewRequirementsRequest $request): JsonResponse
    {
        $requirements = config('academic-mobility.models.requirement')::query()
            ->applicableTo(
                $request->enum('direction', MobilityDirection::class),
                $request->enum('scope', MobilityScope::class),
                $request->enum('type', ParticipantKind::class),
            )
            ->ordered()
            ->get();

        return Response::api(RequirementResource::collection($requirements)->resolve($request));
    }

    /**
     * A mobility with the requirements it was created with, and who takes part.
     */
    public function show(Request $request, Mobility $mobility): JsonResponse
    {
        Gate::authorize('view', $mobility);

        $mobility->load(['originInstitution', 'destinationInstitution', 'requirements']);

        $participant = $mobility->direction === null
            ? null
            : $this->describe(AcademicMobility::participantResolver($mobility->direction, $mobility->participant_type)->resolve($mobility->participant_id));

        return Response::api([
            ...(new MobilityResource($mobility))->resolve($request),
            'participant' => $participant,
        ]);
    }

    /**
     * Random values for the registration form: invented by the host's resolver (demos and development only).
     */
    public function sampleParticipant(SampleParticipantRequest $request): JsonResponse
    {
        return Response::api($request->resolver()->sample(
            $request->filled('institution_id') ? $request->string('institution_id')->toString() : null,
        ));
    }

    /**
     * Registers a person that did not exist (a visitor of an incoming mobility) and returns it as a participant.
     */
    public function storeParticipant(StoreParticipantRequest $request): JsonResponse
    {
        $participant = $request->resolver()->create(
            $request->validated('data'),
            $request->filled('institution_id') ? $request->string('institution_id')->toString() : null,
        );

        return Response::api($this->describe($participant), __('Participant created'), status: 201);
    }

    /**
     * @return array<string, array<string, array{fields: array<int, array<string, mixed>>, can_sample: bool}>>
     */
    private function participantForms(): array
    {
        $forms = [];

        foreach (MobilityDirection::cases() as $direction) {
            foreach (AcademicMobility::participantKinds($direction) as $kind) {
                $resolver = AcademicMobility::participantResolver($direction, $kind);

                if ($resolver instanceof CreatableParticipantResolver) {
                    $forms[$direction->value][$kind] = [
                        'fields' => $resolver->fields(),
                        // A "fill with random data" button, for demos and development.
                        'can_sample' => config('academic-mobility.samples', false) && $resolver instanceof SampleParticipantResolver,
                    ];
                }
            }
        }

        return $forms;
    }

    /**
     * Partner institutions matching a search, of the chosen scope (the picker of the creation form).
     */
    public function institutions(SearchInstitutionsRequest $request): JsonResponse
    {
        $institutions = config('academic-mobility.models.institution')::query()
            ->partners()
            ->applyFilters($request)
            ->localFirst()
            ->limit(20)
            ->get();

        return Response::api(InstitutionResource::collection($institutions)->resolve($request));
    }

    public function store(StoreMobilityRequest $request): JsonResponse
    {
        try {
            $mobility = AcademicMobility::create(new CreateMobilityData(
                new ParticipantReference($request->string('participant_type')->toString(), $request->string('participant_id')->toString()),
                $request->filled('direction') ? $request->enum('direction', MobilityDirection::class) : null,
                $request->enum('scope', MobilityScope::class),
                $request->string('institution_id')->toString(),
                $request->string('strategy')->toString(),
                array_filter((array) $request->input('metadata', []), fn ($value) => $value !== null),
                $request->filled('agreement_id') ? $request->string('agreement_id')->toString() : null,
            ));
        } catch (ParticipantNotFound) {
            throw ValidationException::withMessages([
                'participant_id' => __('The participant was not found.'),
            ]);
        } catch (UnknownExtension $e) {
            // Only the participant source can be missing here: strategies are validated by the request.
            throw ValidationException::withMessages([
                'participant_type' => __('There is no source of participants of this type for this direction.'),
            ]);
        } catch (AgreementNotApplicable $e) {
            throw ValidationException::withMessages(['agreement_id' => $e->getMessage()]);
        } catch (InstitutionNotFound $e) {
            throw ValidationException::withMessages([$e->field => __('The institution was not found.')]);
        } catch (InvalidRoute $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        } catch (MobilityNotEligible $e) {
            throw ValidationException::withMessages([
                'eligibility' => array_map(fn (RuleResult $failure) => $failure->message ?? $failure->rule, $e->failures),
            ]);
        }

        return Response::api((new MobilityResource($mobility->load(['originInstitution', 'destinationInstitution', 'requirements'])))->resolve($request), __('Mobility created'), status: 201);
    }

    /**
     * Participants of a type matching a search term (the picker of the creation form).
     */
    public function participants(SearchParticipantsRequest $request): JsonResponse
    {
        $resolver = AcademicMobility::participantResolver(
            $request->enum('direction', MobilityDirection::class),
            $request->string('type')->toString(),
        );

        $found = $resolver instanceof SearchableParticipantResolver
            ? $resolver->search(
                $request->string('search')->toString(),
                10,
                $request->filled('institution_id') ? $request->string('institution_id')->toString() : null,
            )
            : [];

        return Response::api(array_map(fn (Participant $participant) => $this->describe($participant), $found));
    }

    /**
     * The signed-in user as each kind of participant they are (a student, a professor...).
     *
     * @return array<int, array<string, mixed>>
     */
    private function currentParticipants(Request $request): array
    {
        $described = [];

        foreach (AcademicMobility::currentParticipants($request->user()) as $reference) {
            if (! in_array($reference->type, AcademicMobility::participantKinds(MobilityDirection::Outgoing), true)) {
                continue;
            }

            $resolver = AcademicMobility::participantResolver(MobilityDirection::Outgoing, $reference->type);

            if ($participant = $this->describe($resolver->resolve($reference->id))) {
                $described[] = $participant;
            }
        }

        return $described;
    }

    /**
     * @return array{type: string, id: string, name: string, details: array<int, array{label: string, value: string}>}|null
     */
    private function describe(?Participant $participant): ?array
    {
        if ($participant === null) {
            return null;
        }

        $details = $participant instanceof DetailedParticipant ? $participant->details() : [];

        return [
            'type' => $participant->participantType(),
            'id' => (string) $participant->participantId(),
            'name' => $participant->displayName(),
            'details' => array_map(fn (string $label, string $value) => compact('label', 'value'), array_keys($details), array_values($details)),
        ];
    }
}
