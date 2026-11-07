<?php

namespace Module\AcademicMobility\Rules;

final class ScopeCondition extends RouteConditions
{
    protected function actual(RuleContext $context): ?string
    {
        return $context->route?->scope->value;
    }
}
