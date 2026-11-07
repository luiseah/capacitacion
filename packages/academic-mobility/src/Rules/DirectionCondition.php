<?php

namespace Module\AcademicMobility\Rules;

final class DirectionCondition extends RouteConditions
{
    protected function actual(RuleContext $context): ?string
    {
        return $context->route?->direction->value;
    }
}
