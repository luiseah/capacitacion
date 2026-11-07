<?php

namespace Module\ApiResponse\Tests;

use Module\ApiResponse\ApiResponseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ApiResponseServiceProvider::class];
    }
}
