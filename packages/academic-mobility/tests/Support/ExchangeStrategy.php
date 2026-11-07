<?php

namespace Module\AcademicMobility\Tests\Support;

use Module\AcademicMobility\Contracts\MobilityStrategy;

final class ExchangeStrategy implements MobilityStrategy
{
    public function rules(): array
    {
        return [
            ['rule' => 'active_enrollment'],
            [
                'rule' => 'participant_attribute',
                'params' => ['attribute' => 'gpa', 'operator' => '>=', 'value' => 4.0],
                'message' => 'GPA must be at least 4.0.',
                'when' => ['evaluator' => 'abroad'],
            ],
        ];
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
