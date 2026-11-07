<?php

namespace Module\AcademicMobility\Tests\Support;

use Module\AcademicMobility\Contracts\AffiliatedParticipant;
use Module\AcademicMobility\Contracts\DetailedParticipant;
use Module\AcademicMobility\Contracts\Participant;

/**
 * Stands in for a host's student: the package tests need no host models.
 */
final class FakeParticipant implements AffiliatedParticipant, DetailedParticipant, Participant
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        private readonly string|int $id,
        private readonly array $attributes = [],
        private readonly string $type = 'student',
    ) {}

    public function participantType(): string
    {
        return $this->type;
    }

    public function participantId(): string|int
    {
        return $this->id;
    }

    public function displayName(): string
    {
        return "Participant {$this->id}";
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function details(): array
    {
        return isset($this->attributes['enrollment']) ? ['Enrollment' => (string) $this->attributes['enrollment']] : [];
    }

    public function institutionId(): ?string
    {
        return $this->attributes['institution_id'] ?? null;
    }
}
