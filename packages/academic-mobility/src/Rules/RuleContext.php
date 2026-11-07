<?php

namespace Module\AcademicMobility\Rules;

use Module\AcademicMobility\Contracts\Participant;
use Module\AcademicMobility\Core\ValueObjects\MobilityRoute;

/**
 * What a rule can look at: the participant, where the mobility goes and the
 * data of the mobility being requested.
 */
final readonly class RuleContext
{
    /**
     * @param  array<string, mixed>  $mobility
     */
    public function __construct(
        public Participant $participant,
        public array $mobility = [],
        public ?MobilityRoute $route = null,
    ) {}
}
