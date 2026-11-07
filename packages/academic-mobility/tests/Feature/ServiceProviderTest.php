<?php

namespace Module\AcademicMobility\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Module\AcademicMobility\AcademicMobility;
use Module\AcademicMobility\AcademicMobilityServiceProvider;
use Module\AcademicMobility\Core\Persistence\EloquentMobilityRepository;
use Module\AcademicMobility\Core\Persistence\MobilityRepository;
use Module\AcademicMobility\Facades\AcademicMobility as Facade;
use Module\AcademicMobility\Support\ExtensionKind;
use Module\AcademicMobility\Tests\Support\ActiveEnrollmentRule;
use Module\AcademicMobility\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_registers_config_services_and_facade(): void
    {
        $this->assertSame('default', config('academic-mobility.default_strategy'));
        $this->assertInstanceOf(AcademicMobility::class, app(AcademicMobility::class));
        $this->assertSame(app(AcademicMobility::class), Facade::getFacadeRoot());
        $this->assertInstanceOf(EloquentMobilityRepository::class, app(MobilityRepository::class));
    }

    public function test_ships_builtin_extensions(): void
    {
        $this->assertTrue(Facade::has(ExtensionKind::Rule, 'participant_attribute'));
        $this->assertTrue(Facade::has(ExtensionKind::Strategy, 'default'));
    }

    public function test_loads_the_migration_with_the_configured_prefix(): void
    {
        $this->assertTrue(Schema::hasTable('academic_mobilities'));
    }

    public function test_publishes_config_and_migrations(): void
    {
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(AcademicMobilityServiceProvider::class, 'academic-mobility-config'));
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(AcademicMobilityServiceProvider::class, 'academic-mobility-migrations'));
    }

    public function test_host_config_extensions_are_registered(): void
    {
        $this->app['config']->set('academic-mobility.rules', ['active_enrollment' => ActiveEnrollmentRule::class]);
        $this->app->forgetInstance(\Module\AcademicMobility\Support\ExtensionRegistry::class);
        $this->app->forgetInstance(AcademicMobility::class);

        $this->assertInstanceOf(ActiveEnrollmentRule::class, app(AcademicMobility::class)->extension(ExtensionKind::Rule, 'active_enrollment'));
        $this->assertTrue(app(AcademicMobility::class)->has(ExtensionKind::Rule, 'participant_attribute'));
    }
}
