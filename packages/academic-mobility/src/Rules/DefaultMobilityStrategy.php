<?php

namespace Module\AcademicMobility\Rules;

use Module\AcademicMobility\Contracts\MobilityStrategy;

/**
 * Built-in strategy `default`: no rules, documents or approvers. Hosts replace
 * it (same alias) or add their own strategies next to it.
 */
class DefaultMobilityStrategy implements MobilityStrategy
{
    public function rules(): array
    {
        return [];
    }

    public function documentProviders(): array
    {
        return [];
    }

    public function approvers(): array
    {
        return [];
    }
}
