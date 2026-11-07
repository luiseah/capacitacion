<?php

namespace Module\AcademicMobility\Tests\Support;

use Module\AcademicMobility\Contracts\DirectionalStrategy;
use Module\AcademicMobility\Contracts\MobilityStrategy;
use Module\AcademicMobility\Core\Enums\MobilityDirection;

/** A strategy only for people going out: choosing it fixes the direction. */
final class OutgoingExchangeStrategy implements DirectionalStrategy, MobilityStrategy
{
    public function direction(): MobilityDirection
    {
        return MobilityDirection::Outgoing;
    }

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
