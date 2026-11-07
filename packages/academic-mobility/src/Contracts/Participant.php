<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Package-side view of whoever takes part in a mobility (student, professor,
 * staff...). The host application adapts its own models to this contract; the
 * package never touches `User`, `Student` or any other host class.
 */
interface Participant
{
    /** Alias of the participant kind, e.g. `student`. Matches its resolver alias. */
    public function participantType(): string;

    public function participantId(): string|int;

    public function displayName(): string;

    /**
     * Facts the rules can evaluate, e.g. `['enrollment_status' => 'active', 'gpa' => 4.2]`.
     * Dot notation reaches nested values.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array;
}
