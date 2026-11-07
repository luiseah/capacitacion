<?php

namespace Module\AcademicMobility\Core\Exceptions;

use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;

class ParticipantNotFound extends AcademicMobilityException
{
    public static function make(ParticipantReference $reference): self
    {
        return new self("Participant [{$reference->type}:{$reference->id}] was not found by its resolver.");
    }
}
