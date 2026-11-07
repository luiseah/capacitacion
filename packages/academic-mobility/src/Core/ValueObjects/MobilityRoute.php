<?php

namespace Module\AcademicMobility\Core\ValueObjects;

use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Where a mobility goes: the two institutions and what follows from them.
 */
final readonly class MobilityRoute
{
    public function __construct(
        public Institution $origin,
        public Institution $destination,
        public MobilityDirection $direction,
        public MobilityScope $scope,
    ) {}
}
