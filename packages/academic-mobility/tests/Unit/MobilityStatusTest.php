<?php

namespace Module\AcademicMobility\Tests\Unit;

use Module\AcademicMobility\Core\Enums\MobilityStatus;
use PHPUnit\Framework\TestCase;

class MobilityStatusTest extends TestCase
{
    public function test_follows_the_allowed_transitions(): void
    {
        $this->assertTrue(MobilityStatus::Draft->canTransitionTo(MobilityStatus::Submitted));
        $this->assertTrue(MobilityStatus::UnderReview->canTransitionTo(MobilityStatus::Approved));
        $this->assertFalse(MobilityStatus::Draft->canTransitionTo(MobilityStatus::Approved));
        $this->assertFalse(MobilityStatus::Approved->canTransitionTo(MobilityStatus::Draft));
    }

    public function test_final_statuses_allow_nothing(): void
    {
        foreach ([MobilityStatus::Rejected, MobilityStatus::Cancelled, MobilityStatus::Completed] as $status) {
            $this->assertTrue($status->isFinal());
        }

        $this->assertFalse(MobilityStatus::Draft->isFinal());
    }
}
