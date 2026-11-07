<?php

use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;

return [

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    | `connection`: database connection (null = the application's default).
    | `table_prefix`: tables are `{prefix}mobilities`.
    | `load_migrations`: load the package migrations directly. Set it to false
    | after publishing them (`--tag=academic-mobility-migrations`) to own them.
    | `models.mobility`: your own model; it must extend the package's Mobility.
    */
    'connection' => env('ACADEMIC_MOBILITY_DB_CONNECTION'),
    'table_prefix' => 'academic_',
    'load_migrations' => true,

    'models' => [
        'mobility' => Mobility::class,
        'institution' => Institution::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    | Routes of the mobilities list (JSON API + Inertia page). `enabled: false`
    | skips them. Middleware replaces the defaults, so list the auth you need
    | (e.g. `['api', 'auth:sanctum']` for the API of a Sanctum SPA: the `api` group is what makes Sanctum accept the session cookie).
    | The API answers with the envelope `{ status, message, data, ...pagination }`.
    */
    'routes' => [
        'enabled' => true,
        'web_prefix' => 'academic-mobility',
        'api_prefix' => 'api/academic-mobility',
        'web_middleware' => ['web', 'auth'],
        'api_middleware' => ['api', 'auth:sanctum'],
    ],

    'per_page' => 15,

    // "Fill with random data" in the visitor registration form: only while developing.
    'samples' => env('ACADEMIC_MOBILITY_SAMPLES', env('APP_ENV') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Strategy
    |--------------------------------------------------------------------------
    | Alias of the strategy used when a mobility does not name one.
    */
    'default_strategy' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    | `alias => class-string`. The package registers its own defaults; the
    | host application adds (or replaces, by reusing an alias) from here or at
    | runtime with the AcademicMobility facade:
    |
    |   AcademicMobility::registerRule('active_enrollment', ActiveEnrollmentRule::class);
    |
    | Each class is built by the Laravel container, so it can type-hint its own
    | dependencies. At runtime a Closure `fn (Application $app) => new Thing(...)` or
    | an instance also works (not here: `config:cache` cannot serialize them).
    */
    'rules' => [],
    'strategies' => [],
    'condition_evaluators' => [],
    'participant_resolvers' => [],
    'document_providers' => [],
    'approvers' => [],
];
