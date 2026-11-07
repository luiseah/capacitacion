<?php

namespace Module\AcademicMobility\Contracts;

use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Documents\DocumentRequirement;

interface DocumentProvider
{
    /**
     * @return array<int, DocumentRequirement>
     */
    public function requirements(Mobility $mobility): array;
}
