<?php

namespace Module\AcademicMobility\Rules;

use Module\AcademicMobility\Contracts\ConditionEvaluator;
use Module\AcademicMobility\Contracts\Rule;
use Module\AcademicMobility\Support\ExtensionKind;
use Module\AcademicMobility\Support\ExtensionRegistry;

/**
 * Runs rule definitions (see MobilityStrategy::rules()) against a context.
 */
class RuleEngine
{
    public function __construct(private readonly ExtensionRegistry $extensions) {}

    /**
     * Every definition that applies is evaluated; none short-circuits, so the
     * caller can report all the failures at once.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, RuleResult>
     */
    public function evaluate(array $definitions, RuleContext $context): array
    {
        $results = [];

        foreach ($definitions as $definition) {
            if (! $this->applies($definition['when'] ?? null, $context)) {
                continue;
            }

            $alias = $definition['rule'];

            /** @var Rule $rule */
            $rule = $this->extensions->resolve(ExtensionKind::Rule, $alias);

            $results[] = $rule
                ->evaluate($context, $definition['params'] ?? [])
                ->forRule($alias, $definition['message'] ?? null);
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, RuleResult>
     */
    public function failures(array $definitions, RuleContext $context): array
    {
        return array_values(array_filter(
            $this->evaluate($definitions, $context),
            fn (RuleResult $result) => ! $result->passed,
        ));
    }

    /**
     * @param  array<string, mixed>|null  $when
     */
    private function applies(?array $when, RuleContext $context): bool
    {
        if ($when === null) {
            return true;
        }

        /** @var ConditionEvaluator $evaluator */
        $evaluator = $this->extensions->resolve(ExtensionKind::ConditionEvaluator, $when['evaluator']);

        return $evaluator->evaluate($context, $when['params'] ?? []);
    }
}
