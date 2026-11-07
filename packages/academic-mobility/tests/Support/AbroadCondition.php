<?php

namespace Module\AcademicMobility\Tests\Support;

use Module\AcademicMobility\Contracts\ConditionEvaluator;
use Module\AcademicMobility\Rules\RuleContext;

/** Applies only to mobilities abroad. */
final class AbroadCondition implements ConditionEvaluator
{
    public function evaluate(RuleContext $context, array $params = []): bool
    {
        return ($context->mobility['destination'] ?? null) === 'abroad';
    }
}
