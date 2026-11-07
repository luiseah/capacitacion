<?php

namespace Module\AcademicMobility\Core\Exceptions;

class InvalidAgreement extends AcademicMobilityException
{
    public static function withTheLocalInstitution(): self
    {
        return new self(__('An agreement is with a partner institution, not with the local one.'));
    }

    public static function endsBeforeItStarts(): self
    {
        return new self(__('An agreement cannot end before it starts.'));
    }

    public static function withoutActivities(): self
    {
        return new self(__('An agreement covers at least one activity.'));
    }

    public static function frameworkWithAFramework(): self
    {
        return new self(__('A framework agreement does not depend on another one.'));
    }

    public static function notAFrameworkOfTheInstitution(): self
    {
        return new self(__('A specific agreement can only depend on a framework agreement of the same institution.'));
    }

    public static function bilateralWithSeveralInstitutions(): self
    {
        return new self(__('A bilateral agreement is with one institution only.'));
    }

    public static function multilateralNeedsMoreInstitutions(): self
    {
        return new self(__('A multilateral agreement needs at least one more institution.'));
    }

    public static function invalidInstitutionsOfAMultilateral(): self
    {
        return new self(__('The other institutions of an agreement must be partner institutions, different from its main one.'));
    }
}
