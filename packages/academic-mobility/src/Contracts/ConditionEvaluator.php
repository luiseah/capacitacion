<?php

namespace Module\AcademicMobility\Contracts;

use Module\AcademicMobility\Rules\RuleContext;

/**
 * Decides whether a rule applies at all (its `when` condition), e.g. "only
 * for exchanges abroad".
 */
interface ConditionEvaluator
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function evaluate(RuleContext $context, array $params = []): bool;
}
