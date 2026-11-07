<?php

namespace Module\AcademicMobility\Core\Enums;

/**
 * Seen from the local institution: people coming in or going out.
 */
enum MobilityDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
}
