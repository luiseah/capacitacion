<?php

namespace Module\AcademicMobility\Tests\Support;

use Module\AcademicMobility\Contracts\Rule;
use Module\AcademicMobility\Rules\RuleContext;
use Module\AcademicMobility\Rules\RuleResult;

/** Host-style rule: the participant needs `enrollment` = active. */
final class ActiveEnrollmentRule implements Rule
{
    public function evaluate(RuleContext $context, array $params = []): RuleResult
    {
        return ($context->participant->attributes()['enrollment'] ?? null) === 'active'
            ? RuleResult::pass()
            : RuleResult::fail('Enrollment must be active.');
    }
}
