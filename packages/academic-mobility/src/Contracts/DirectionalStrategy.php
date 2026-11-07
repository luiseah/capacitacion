<?php

namespace Module\AcademicMobility\Contracts;

use Module\AcademicMobility\Core\Enums\MobilityDirection;

/**
 * Optional companion of {@see MobilityStrategy}: a strategy that is only for
 * incoming or only for outgoing mobilities (e.g. "student exchange abroad").
 * Choosing it fixes the direction, so the form does not ask for it. A strategy
 * that does not implement this serves both and the direction is chosen with it.
 */
interface DirectionalStrategy
{
    public function direction(): MobilityDirection;
}
