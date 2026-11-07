<?php

namespace Module\AcademicMobility\Core\ValueObjects;

use Module\AcademicMobility\Contracts\Participant;

/**
 * Pointer to a participant owned by the host application: its type alias and
 * id. This is all the package stores about them.
 */
final readonly class ParticipantReference
{
    public string $type;

    public string $id;

    public function __construct(string $type, string|int $id)
    {
        $this->type = $type;
        $this->id = (string) $id;
    }

    public static function of(Participant $participant): self
    {
        return new self($participant->participantType(), $participant->participantId());
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
