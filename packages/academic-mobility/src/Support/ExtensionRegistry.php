<?php

namespace Module\AcademicMobility\Support;

use Closure;
use Illuminate\Contracts\Container\Container;
use Module\AcademicMobility\Contracts\ParticipantResolver;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Exceptions\InvalidExtension;
use Module\AcademicMobility\Core\Exceptions\UnknownExtension;

/**
 * Alias => implementation map for every {@see ExtensionKind}.
 *
 * An extension can be registered as a class-string (built by the container, so
 * it can inject its own dependencies), a Closure receiving the container, or a
 * ready instance. Registering an alias again replaces the previous one, which
 * is how a host overrides a default. Resolved instances are shared, so
 * extensions must be stateless.
 */
class ExtensionRegistry
{
    /** @var array<string, array<string, string|object>> */
    private array $definitions = [];

    /** @var array<string, array<string, object>> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    public function register(ExtensionKind $kind, string $alias, string|object $extension): void
    {
        if ($kind === ExtensionKind::ParticipantResolver && ! $this->isParticipantAlias($alias)) {
            throw InvalidExtension::make($kind, $alias, 'participants are one of ['.implode(', ', ParticipantKind::values()).'], optionally prefixed by the direction (incoming.student)');
        }

        if (is_string($extension)) {
            if (! class_exists($extension)) {
                throw InvalidExtension::make($kind, $alias, "class [{$extension}] does not exist");
            }

            if (! is_subclass_of($extension, $kind->contract())) {
                throw InvalidExtension::make($kind, $alias, "[{$extension}] must implement [{$kind->contract()}]");
            }
        } elseif (! $extension instanceof Closure && ! $extension instanceof ($kind->contract())) {
            throw InvalidExtension::make($kind, $alias, 'the instance must implement ['.$kind->contract().']');
        }

        $this->definitions[$kind->value][$alias] = $extension;
        unset($this->resolved[$kind->value][$alias]);
    }

    public function has(ExtensionKind $kind, string $alias): bool
    {
        return isset($this->definitions[$kind->value][$alias]);
    }

    /**
     * @return array<int, string>
     */
    public function aliases(ExtensionKind $kind): array
    {
        return array_keys($this->definitions[$kind->value] ?? []);
    }

    public function resolve(ExtensionKind $kind, string $alias): object
    {
        return $this->resolved[$kind->value][$alias] ??= $this->build($kind, $alias);
    }

    private function build(ExtensionKind $kind, string $alias): object
    {
        $definition = $this->definitions[$kind->value][$alias] ?? throw UnknownExtension::make($kind, $alias);

        $instance = match (true) {
            is_string($definition) => $this->container->make($definition),
            $definition instanceof Closure => $definition($this->container),
            default => $definition,
        };

        $contract = $kind->contract();

        if (! $instance instanceof $contract) {
            throw InvalidExtension::make($kind, $alias, "it built a [".get_debug_type($instance)."], expected [{$contract}]");
        }

        return $instance;
    }

    /**
     * Where the participants of a direction come from: the resolver of `incoming.student` (visitors)
     * or `outgoing.student` (the university's own people). A bare `student` serves both directions.
     *
     * @throws UnknownExtension
     */
    public function participantResolver(MobilityDirection $direction, string $kind): ParticipantResolver
    {
        $alias = $this->has(ExtensionKind::ParticipantResolver, "{$direction->value}.{$kind}") ? "{$direction->value}.{$kind}" : $kind;

        /** @var ParticipantResolver */
        return $this->resolve(ExtensionKind::ParticipantResolver, $alias);
    }

    /**
     * The kinds that have a source for the direction, in the order of the enum.
     *
     * @return array<int, string>
     */
    public function participantKinds(MobilityDirection $direction): array
    {
        return array_values(array_filter(
            ParticipantKind::values(),
            fn (string $kind) => $this->has(ExtensionKind::ParticipantResolver, "{$direction->value}.{$kind}")
                || $this->has(ExtensionKind::ParticipantResolver, $kind),
        ));
    }

    private function isParticipantAlias(string $alias): bool
    {
        $parts = explode('.', $alias, 2);
        $kind = array_pop($parts);

        return ParticipantKind::tryFrom($kind) !== null
            && ($parts === [] || MobilityDirection::tryFrom($parts[0]) !== null);
    }
}
