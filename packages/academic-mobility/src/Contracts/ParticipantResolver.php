<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Turns a stored participant id into a {@see Participant}. One resolver per
 * participant type, registered under the type's alias.
 */
interface ParticipantResolver
{
    public function resolve(string|int $id): ?Participant;
}
