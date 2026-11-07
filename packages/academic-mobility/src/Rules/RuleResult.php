<?php

namespace Module\AcademicMobility\Rules;

final readonly class RuleResult
{
    /**
     * @param  string  $rule  Alias of the rule that produced it (filled by the engine).
     */
    public function __construct(
        public bool $passed,
        public ?string $message = null,
        public string $rule = '',
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }

    public function forRule(string $rule, ?string $message = null): self
    {
        return new self($this->passed, $this->passed ? $this->message : ($message ?? $this->message), $rule);
    }
}
