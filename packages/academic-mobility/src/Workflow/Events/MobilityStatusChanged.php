<?php

namespace Module\AcademicMobility\Workflow\Events;

use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Models\Mobility;

final readonly class MobilityStatusChanged
{
    public function __construct(
        public Mobility $mobility,
        public MobilityStatus $from,
        public MobilityStatus $to,
    ) {}
}
