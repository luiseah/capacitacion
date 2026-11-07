<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Optional companion of {@see ParticipantResolver}: lets the creation form
 * offer a search box for this kind of participant instead of a bare id.
 */
interface SearchableParticipantResolver extends ParticipantResolver
{
    /**
     * @param  string|null  $institutionId  For incoming mobilities: only people of this origin institution.
     * @return array<int, Participant>
     */
    public function search(string $term, int $limit = 10, ?string $institutionId = null): array;
}
