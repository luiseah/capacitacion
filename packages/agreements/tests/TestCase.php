<?php

namespace Module\Agreements\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\AcademicMobility\AcademicMobilityServiceProvider;
use Module\Agreements\AgreementsServiceProvider;
use Module\ApiResponse\ApiResponseServiceProvider;
use Module\Pagination\PaginationServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // Not listed by hand in a real app: Laravel auto-discovers them.
        return [ApiResponseServiceProvider::class, PaginationServiceProvider::class, AcademicMobilityServiceProvider::class, AgreementsServiceProvider::class];
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
