<?php

namespace Module\AcademicMobility;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\AcademicMobility\Authorization\MobilityPolicy;
use Module\AcademicMobility\Authorization\AgreementPolicy;
use Module\AcademicMobility\Authorization\InstitutionPolicy;
use Module\AcademicMobility\Authorization\RequirementPolicy;
use Module\AcademicMobility\Core\Models\Agreement;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\Models\Requirement;
use Module\AcademicMobility\Core\Persistence\EloquentMobilityRepository;
use Module\AcademicMobility\Core\Persistence\MobilityRepository;
use Module\AcademicMobility\Rules\DefaultMobilityStrategy;
use Module\AcademicMobility\Rules\DirectionCondition;
use Module\AcademicMobility\Rules\ScopeCondition;
use Module\AcademicMobility\Rules\ParticipantAttributeRule;
use Module\AcademicMobility\Support\ExtensionKind;
use Module\AcademicMobility\Support\ExtensionRegistry;

class AcademicMobilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/academic-mobility.php', 'academic-mobility');

        // `mergeConfigFrom` is shallow: a published config written before a model was added to
        // `models` would hide the new default, so fill the missing keys of that map.
        $defaults = require __DIR__.'/../config/academic-mobility.php';
        config(['academic-mobility.models' => [...$defaults['models'], ...(array) config('academic-mobility.models', [])]]);

        // `bindIf`: a host that bound its own implementation first keeps it.
        $this->app->bindIf(MobilityRepository::class, EloquentMobilityRepository::class);

        // Built lazily, so the host's config and its own providers have run by
        // the time the first extension is registered or resolved.
        $this->app->singleton(ExtensionRegistry::class, function ($app) {
            $registry = new ExtensionRegistry($app);

            $registry->register(ExtensionKind::Rule, 'participant_attribute', ParticipantAttributeRule::class);
            $registry->register(ExtensionKind::Strategy, 'default', DefaultMobilityStrategy::class);
            $registry->register(ExtensionKind::ConditionEvaluator, 'direction', DirectionCondition::class);
            $registry->register(ExtensionKind::ConditionEvaluator, 'scope', ScopeCondition::class);

            foreach (ExtensionKind::cases() as $kind) {
                foreach ((array) config("academic-mobility.{$kind->configKey()}", []) as $alias => $extension) {
                    $registry->register($kind, $alias, $extension);
                }
            }

            return $registry;
        });

        $this->app->singleton(AcademicMobility::class);
    }

    public function boot(): void
    {
        // `lang/{locale}.json`: English text => translation, merged with the application's.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        if (config('academic-mobility.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        Gate::policy(config('academic-mobility.models.mobility', Mobility::class), MobilityPolicy::class);
        Gate::policy(config('academic-mobility.models.institution', Institution::class), InstitutionPolicy::class);
        Gate::policy(config('academic-mobility.models.requirement', Requirement::class), RequirementPolicy::class);
        Gate::policy(config('academic-mobility.models.agreement', Agreement::class), AgreementPolicy::class);

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/academic-mobility.php' => config_path('academic-mobility.php'),
            ], 'academic-mobility-config');

            $this->publishes([
                __DIR__.'/../database/migrations/2026_11_06_080000_create_academic_mobilities_table.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_academic_mobilities_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_010000_create_academic_institutions_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_academic_institutions_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_020000_add_route_to_academic_mobilities_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 2).'_add_route_to_academic_mobilities_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_080000_create_academic_requirements_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 3).'_create_academic_requirements_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_100000_create_academic_agreements_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 4).'_create_academic_agreements_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_110000_add_type_to_academic_agreements_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 5).'_add_type_to_academic_agreements_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_120000_rename_academic_agreement_activities.php' => database_path('migrations/'.date('Y_m_d_His', time() + 6).'_rename_academic_agreement_activities.php'),
                __DIR__.'/../database/migrations/2026_11_07_130000_add_parties_to_academic_agreements_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 7).'_add_parties_to_academic_agreements_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_140000_add_agreement_to_academic_mobilities_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 8).'_add_agreement_to_academic_mobilities_table.php'),
                __DIR__.'/../database/migrations/2026_11_07_150000_add_details_to_academic_agreements_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 9).'_add_details_to_academic_agreements_table.php'),
            ], 'academic-mobility-migrations');

            $this->publishes([
                __DIR__.'/../resources/js' => resource_path('js/vendor/academic-mobility'),
            ], 'academic-mobility-react');
        }
    }

    private function registerRoutes(): void
    {
        if (! config('academic-mobility.routes.enabled', true)) {
            return;
        }

        // `{institution}` resolves to the configured model (a host may swap it).
        Route::bind('agreement', fn (string $id) => config('academic-mobility.models.agreement', Agreement::class)::query()->findOrFail($id));
        Route::bind('requirement', fn (string $id) => config('academic-mobility.models.requirement', Requirement::class)::query()->findOrFail($id));
        Route::bind('mobility', fn (string $id) => config('academic-mobility.models.mobility', Mobility::class)::query()->findOrFail($id));
        Route::bind('institution', fn (string $id) => config('academic-mobility.models.institution', Institution::class)::query()->findOrFail($id));

        Route::middleware(config('academic-mobility.routes.api_middleware', ['auth']))
            ->prefix(config('academic-mobility.routes.api_prefix', 'api/academic-mobility'))
            ->name('academic-mobility.api.')
            ->group(__DIR__.'/../routes/api.php');

        // The page needs Inertia; without it only the API is available.
        if (class_exists(\Inertia\Inertia::class)) {
            Route::middleware(config('academic-mobility.routes.web_middleware', ['web', 'auth']))
                ->prefix(config('academic-mobility.routes.web_prefix', 'academic-mobility'))
                ->name('academic-mobility.')
                ->group(__DIR__.'/../routes/web.php');
        }
    }
}
