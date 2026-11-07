<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Optional companion of {@see Participant} for people that belong to another
 * institution (the visitors of an incoming mobility). The package checks that
 * they come from the origin institution that was chosen.
 */
interface AffiliatedParticipant
{
    /** Id of the {@see \Module\AcademicMobility\Core\Models\Institution} the person belongs to. */
    public function institutionId(): ?string;
}
