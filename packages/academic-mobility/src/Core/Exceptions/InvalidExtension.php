<?php

namespace Module\AcademicMobility\Core\Exceptions;

use Module\AcademicMobility\Support\ExtensionKind;

class InvalidExtension extends AcademicMobilityException
{
    public static function make(ExtensionKind $kind, string $alias, string $reason): self
    {
        return new self("Cannot use [{$alias}] as {$kind->label()}: {$reason}.");
    }
}
