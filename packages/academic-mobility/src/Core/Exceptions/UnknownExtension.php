<?php

namespace Module\AcademicMobility\Core\Exceptions;

use Module\AcademicMobility\Support\ExtensionKind;

class UnknownExtension extends AcademicMobilityException
{
    public static function make(ExtensionKind $kind, string $alias): self
    {
        return new self("No {$kind->label()} registered with the alias [{$alias}].");
    }
}
