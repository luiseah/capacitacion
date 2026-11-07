<?php

namespace Module\AcademicMobility\Core\Enums;

/**
 * Where an agreement stands: it is drafted, in process (being signed), in force, expired, suspended
 * or terminated. An agreement recorded as in force whose end date has passed is reported as expired,
 * and only one in force, within its dates, is usable.
 */
enum AgreementStatus: string
{
    case Draft = 'draft';
    case InProcess = 'in_process';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
