<?php

namespace Module\Agreements\Enums;

/**
 * What happens when an agreement reaches its end date.
 */
enum AgreementRenewal: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    /** It is not renewable: it ends on its end date. */
    case None = 'none';
}
