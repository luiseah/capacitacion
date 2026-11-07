<?php

namespace Module\AcademicMobility\Contracts;

use Module\AcademicMobility\Rules\RuleContext;
use Module\AcademicMobility\Rules\RuleResult;

/**
 * An eligibility / validation rule. Stateless: parameters arrive per call, so
 * one instance can serve every strategy that uses it.
 */
interface Rule
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function evaluate(RuleContext $context, array $params = []): RuleResult;
}
