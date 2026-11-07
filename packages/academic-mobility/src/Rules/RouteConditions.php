<?php

namespace Module\AcademicMobility\Rules;

use Module\AcademicMobility\Contracts\ConditionEvaluator;

/**
 * Built-in `when` conditions on the route of the mobility:
 *
 *   'when' => ['evaluator' => 'direction', 'params' => ['is' => 'outgoing']]
 *   'when' => ['evaluator' => 'scope', 'params' => ['is' => ['international']]]
 *
 * `is` is one value or a list. Without a route in the context they do not apply.
 */
abstract class RouteConditions implements ConditionEvaluator
{
    abstract protected function actual(RuleContext $context): ?string;

    public function evaluate(RuleContext $context, array $params = []): bool
    {
        $actual = $this->actual($context);

        return $actual !== null && in_array($actual, (array) ($params['is'] ?? []), true);
    }
}
