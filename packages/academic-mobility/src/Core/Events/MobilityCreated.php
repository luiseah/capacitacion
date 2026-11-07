<?php

namespace Module\AcademicMobility\Core\Events;

use Module\AcademicMobility\Core\Models\Mobility;

final readonly class MobilityCreated
{
    public function __construct(public Mobility $mobility) {}
}
