<?php

namespace Module\AcademicMobility\Rules;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Module\AcademicMobility\Contracts\Rule;

/**
 * Built-in rule `participant_attribute`: compares one of the participant's
 * attributes with a value.
 *
 * Params: `attribute` (dot notation), `operator` (=, !=, >, >=, <, <=, in,
 * not_in; default `=`) and `value`. A missing attribute never passes.
 */
class ParticipantAttributeRule implements Rule
{
    public function evaluate(RuleContext $context, array $params = []): RuleResult
    {
        $attribute = $params['attribute'] ?? throw new InvalidArgumentException('The [attribute] param is required.');
        $operator = $params['operator'] ?? '=';
        $expected = $params['value'] ?? null;
        $attributes = $context->participant->attributes();

        if (! Arr::has($attributes, $attribute)) {
            return RuleResult::fail("The participant has no [{$attribute}].");
        }

        $actual = Arr::get($attributes, $attribute);

        $passed = match ($operator) {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'in' => in_array($actual, (array) $expected, false),
            'not_in' => ! in_array($actual, (array) $expected, false),
            default => throw new InvalidArgumentException("Unknown operator [{$operator}]."),
        };

        return $passed
            ? RuleResult::pass()
            : RuleResult::fail("[{$attribute}] does not satisfy [{$operator}] ".json_encode($expected).'.');
    }
}
