<?php

namespace Module\AcademicMobility\Tests\Feature;

use Module\AcademicMobility\AcademicMobilityServiceProvider;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Tests\TestCase;

class StaleConfigTest extends TestCase
{
    public function test_models_added_later_keep_their_default(): void
    {
        // A config published before `models.institution` existed, as the host's one was.
        config(['academic-mobility.models' => ['mobility' => Mobility::class]]);

        (new AcademicMobilityServiceProvider($this->app))->register();

        $this->assertSame(Institution::class, config('academic-mobility.models.institution'));
        $this->assertSame(Mobility::class, config('academic-mobility.models.mobility'));
    }
}
