<?php

namespace Module\Agreements;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Module\AcademicMobility\Contracts\AgreementValidator;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\Agreements\Authorization\AgreementPolicy;
use Module\Agreements\Models\Agreement;
use Module\Agreements\Support\MobilityAgreementValidator;

/**
 * Agreements (convenios) between the partner institutions and the local one. They plug into the
 * academic-mobility package through its seams: the validator of the agreement a mobility is made
 * under, the guard that stops an institution with agreements from being deleted and, in the
 * browser, the slots of the mobility pages (see `resources/js/register.ts`).
 */
class AgreementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/agreements.php', 'agreements');

        $this->app->bind(AgreementValidator::class, MobilityAgreementValidator::class);
    }

    public function boot(): void
    {
        // `lang/{locale}.json`: English text => translation, merged with the application's.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        if (config('academic-mobility.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        Gate::policy(config('agreements.models.agreement', Agreement::class), AgreementPolicy::class);

        AcademicMobility::preventInstitutionDeletionUsing(
            fn (Institution $institution) => config('agreements.models.agreement', Agreement::class)::query()->forInstitution($institution->getKey())->exists()
                ? __('This institution has agreements and cannot be deleted.')
                : null,
        );

        // `$institution->agreements`: the ones it is the main institution of (the package adds it, the mobility package does not know them).
        Institution::resolveRelationUsing('agreements', fn (Institution $institution) => $institution->hasMany(config('agreements.models.agreement', Agreement::class))->latestFirst());

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/agreements.php' => config_path('agreements.php'),
            ], 'agreements-config');

            // Every migration of the folder, stamped with today's date (one second apart, to keep their order).
            $this->publishes(
                collect(glob(__DIR__.'/../database/migrations/*.php'))
                    ->mapWithKeys(fn (string $file, int $i) => [
                        $file => database_path('migrations/'.date('Y_m_d_His', time() + $i).'_'.preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($file))),
                    ])->all(),
                'agreements-migrations',
            );

            $this->publishes([
                __DIR__.'/../resources/js' => resource_path('js/vendor/agreements'),
            ], 'agreements-react');
        }
    }

    private function registerRoutes(): void
    {
        if (! config('agreements.routes.enabled', true)) {
            return;
        }

        Route::bind('agreement', fn (string $id) => config('agreements.models.agreement', Agreement::class)::query()->findOrFail($id));

        Route::middleware(config('agreements.routes.api_middleware', ['auth']))
            ->prefix(config('agreements.routes.api_prefix', 'api/agreements'))
            ->name('agreements.api.')
            ->group(__DIR__.'/../routes/api.php');

        // The pages need Inertia; without it only the API is available.
        if (class_exists(Inertia::class)) {
            Route::middleware(config('agreements.routes.web_middleware', ['web', 'auth']))
                ->prefix(config('agreements.routes.web_prefix', 'agreements'))
                ->name('agreements.')
                ->group(__DIR__.'/../routes/web.php');
        }
    }
}
