<?php

namespace Module\AcademicMobility\Core\Exceptions;

class InstitutionNotFound extends AcademicMobilityException
{
    public function __construct(public readonly string $field, string $id)
    {
        parent::__construct("Institution [{$id}] was not found.");
    }
}
