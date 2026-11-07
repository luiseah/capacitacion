<?php

namespace Module\AcademicMobility\Core\Exceptions;

/**
 * The agreement chosen for a mobility cannot be used for it.
 */
class AgreementNotApplicable extends AcademicMobilityException
{
    public static function notFound(): self
    {
        return new self(__('The agreement was not found.'));
    }

    public static function notOfTheInstitution(): self
    {
        return new self(__('The agreement is not with the institution on the other side of the mobility.'));
    }

    public static function notInForce(): self
    {
        return new self(__('The agreement is not in force: only agreements in force can be used.'));
    }
}
