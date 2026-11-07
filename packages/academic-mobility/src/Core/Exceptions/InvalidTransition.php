<?php

namespace Module\AcademicMobility\Core\Exceptions;

use Module\AcademicMobility\Core\Enums\MobilityStatus;

class InvalidTransition extends AcademicMobilityException
{
    public static function make(MobilityStatus $from, MobilityStatus $to): self
    {
        return new self("A mobility cannot go from [{$from->value}] to [{$to->value}].");
    }
}
