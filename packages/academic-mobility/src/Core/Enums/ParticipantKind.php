<?php

namespace Module\AcademicMobility\Core\Enums;

/**
 * Who travels. It is the alias a participant resolver is registered under
 * (`registerParticipantResolver('student', ...)`) and what a mobility stores as
 * its `participant_type`.
 */
enum ParticipantKind: string
{
    case Student = 'student';
    case Professor = 'professor';
    /** Administrative staff. */
    case Staff = 'staff';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
