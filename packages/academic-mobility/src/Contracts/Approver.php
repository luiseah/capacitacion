<?php

namespace Module\AcademicMobility\Contracts;

use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;

interface Approver
{
    /**
     * Who must approve this mobility at this step.
     *
     * @return array<int, ParticipantReference>
     */
    public function approversFor(Mobility $mobility): array;
}
