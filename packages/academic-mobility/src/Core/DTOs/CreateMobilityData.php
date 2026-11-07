<?php

namespace Module\AcademicMobility\Core\DTOs;

use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;

final readonly class CreateMobilityData
{
    /**
     * The type of mobility is its direction and scope. The local institution is always one end: the direction says which, and `institutionId`
     * is the other one (the origin of an incoming mobility, the destination of an outgoing one).
     * The direction comes from the strategy when it fixes one (`DirectionalStrategy`);
     * otherwise it is required here, and a different one than the strategy's is rejected.
     * The scope must agree with the other institution: national = same country as the local one.
     *
     * @param  string|null  $strategy  Strategy alias; null uses `default_strategy` from the config.
     * @param  array<string, mixed>  $metadata  Free data about the mobility, visible to the rules.
     * @param  string|null  $agreementId  The agreement it is made under: one in force with the institution on the other side.
     */
    public function __construct(
        public ParticipantReference $participant,
        public ?MobilityDirection $direction,
        public MobilityScope $scope,
        public string $institutionId,
        public ?string $strategy = null,
        public array $metadata = [],
        public ?string $agreementId = null,
    ) {}
}
