<?php

namespace Module\AcademicMobility\Core\Exceptions;

use Module\AcademicMobility\Rules\RuleResult;

class MobilityNotEligible extends AcademicMobilityException
{
    /**
     * @param  array<int, RuleResult>  $failures
     */
    public function __construct(public readonly array $failures)
    {
        parent::__construct('The participant is not eligible: '.implode('; ', array_map(
            fn (RuleResult $failure) => $failure->message ?? $failure->rule,
            $failures,
        )));
    }
}
