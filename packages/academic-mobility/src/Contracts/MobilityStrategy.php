<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Describes one kind of mobility (exchange, internship, double degree...):
 * which rules gate it, which documents it needs and who approves it.
 */
interface MobilityStrategy
{
    /**
     * Rule definitions: `['rule' => alias, 'params' => [...], 'message' => ?string, 'when' => ?['evaluator' => alias, 'params' => [...]]]`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rules(): array;

    /**
     * Aliases of the registered document providers.
     *
     * @return array<int, string>
     */
    public function documentProviders(): array;

    /**
     * Aliases of the registered approvers, in approval order.
     *
     * @return array<int, string>
     */
    public function approvers(): array;
}
