<?php

namespace Module\AcademicMobility;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Module\AcademicMobility\Core\Actions\CreateMobility;
use Module\AcademicMobility\Core\DTOs\CreateMobilityData;
use Module\AcademicMobility\Contracts\ParticipantResolver;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;
use Module\AcademicMobility\Support\ExtensionKind;
use Module\AcademicMobility\Support\ExtensionRegistry;
use Module\AcademicMobility\Workflow\Actions\TransitionMobility;

/**
 * Public entry point of the package (facade root). It is the API a host
 * application uses to plug in its own behaviour and to drive the Core.
 *
 * Every `register*` method takes an alias plus a class-string, a Closure
 * receiving the container, or an instance; registering an alias again replaces
 * the previous implementation.
 */
class AcademicMobility
{
    /** @var array<int, Closure(Institution): ?string> */
    private array $institutionGuards = [];

    /** @var (Closure(Authenticatable): (ParticipantReference|array<int, ParticipantReference>|null))|null */
    private ?Closure $currentParticipant = null;

    public function __construct(private readonly ExtensionRegistry $extensions) {}

    /**
     * Who the signed-in user is as a participant, so the creation form can preselect them: a
     * reference, a list of them (a professor that is also a student), or null for users that
     * are not participants: `fn (User $user) => new ParticipantReference('student', $user->student?->id)`.
     *
     * @param  Closure(Authenticatable): (ParticipantReference|array<int, ParticipantReference>|null)  $resolver
     */
    public function resolveCurrentParticipantUsing(Closure $resolver): static
    {
        $this->currentParticipant = $resolver;

        return $this;
    }

    /**
     * Lets the host protect institutions its own data points to (e.g. its table of visitors): the
     * guard returns the reason an institution cannot be deleted, or null when it can.
     *
     * @param  Closure(Institution): ?string  $guard
     */
    public function preventInstitutionDeletionUsing(Closure $guard): static
    {
        $this->institutionGuards[] = $guard;

        return $this;
    }

    /**
     * Why the institution cannot be deleted, according to the host; null when nothing opposes it.
     */
    public function institutionDeletionBlockedBy(Institution $institution): ?string
    {
        foreach ($this->institutionGuards as $guard) {
            if (($reason = $guard($institution)) !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * @return array<int, ParticipantReference>
     */
    public function currentParticipants(?Authenticatable $user): array
    {
        if ($user === null || $this->currentParticipant === null) {
            return [];
        }

        $found = ($this->currentParticipant)($user);

        return array_values(array_filter(is_array($found) ? $found : [$found]));
    }

    public function registerRule(string $alias, string|object $rule): static
    {
        return $this->register(ExtensionKind::Rule, $alias, $rule);
    }

    public function registerStrategy(string $alias, string|object $strategy): static
    {
        return $this->register(ExtensionKind::Strategy, $alias, $strategy);
    }

    public function registerConditionEvaluator(string $alias, string|object $evaluator): static
    {
        return $this->register(ExtensionKind::ConditionEvaluator, $alias, $evaluator);
    }

    /**
     * Where the participants of a kind come from. Outgoing mobilities send the university's own
     * people and incoming ones receive visitors from another institution, so each direction can
     * have its own source: `registerParticipantResolver('student', $visitors, MobilityDirection::Incoming)`.
     * Without a direction the resolver serves both.
     */
    public function registerParticipantResolver(string $type, string|object $resolver, ?MobilityDirection $direction = null): static
    {
        return $this->register(
            ExtensionKind::ParticipantResolver,
            $direction === null ? $type : "{$direction->value}.{$type}",
            $resolver,
        );
    }

    public function participantResolver(MobilityDirection $direction, string $kind): ParticipantResolver
    {
        return $this->extensions->participantResolver($direction, $kind);
    }

    /**
     * @return array<int, string>
     */
    public function participantKinds(MobilityDirection $direction): array
    {
        return $this->extensions->participantKinds($direction);
    }

    public function registerDocumentProvider(string $alias, string|object $provider): static
    {
        return $this->register(ExtensionKind::DocumentProvider, $alias, $provider);
    }

    public function registerApprover(string $alias, string|object $approver): static
    {
        return $this->register(ExtensionKind::Approver, $alias, $approver);
    }

    public function register(ExtensionKind $kind, string $alias, string|object $extension): static
    {
        $this->extensions->register($kind, $alias, $extension);

        return $this;
    }

    public function has(ExtensionKind $kind, string $alias): bool
    {
        return $this->extensions->has($kind, $alias);
    }

    /**
     * @return array<int, string>
     */
    public function aliases(ExtensionKind $kind): array
    {
        return $this->extensions->aliases($kind);
    }

    public function extension(ExtensionKind $kind, string $alias): object
    {
        return $this->extensions->resolve($kind, $alias);
    }

    public function create(CreateMobilityData $data): Mobility
    {
        return app(CreateMobility::class)->handle($data);
    }

    public function transition(Mobility $mobility, MobilityStatus $to): Mobility
    {
        return app(TransitionMobility::class)->handle($mobility, $to);
    }
}
