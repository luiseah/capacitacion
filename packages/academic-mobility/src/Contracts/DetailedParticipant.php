<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Optional companion of {@see Participant}: facts about the person to show next
 * to the name in the creation form (a student's code and program, a professor's
 * faculty...). They are only for display; rules read {@see Participant::attributes()}.
 */
interface DetailedParticipant
{
    /**
     * Label => value, in display order. Labels should already be translated.
     *
     * @return array<string, string>
     */
    public function details(): array;
}
