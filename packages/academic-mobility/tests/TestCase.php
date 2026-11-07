<?php

namespace Module\AcademicMobility\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\AcademicMobility\AcademicMobilityServiceProvider;
use Module\ApiResponse\ApiResponseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // Not listed by hand in a real app: Laravel auto-discovers it.
        return [ApiResponseServiceProvider::class, AcademicMobilityServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
