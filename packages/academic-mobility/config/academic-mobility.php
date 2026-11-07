<?php

use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Core\Models\Requirement;

return [

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    | `connection`: database connection (null = the application's default).
    | `table_prefix`: tables are `{prefix}mobilities`.
    | `load_migrations`: load the package migrations directly. Set it to false
    | after publishing them (`--tag=academic-mobility-migrations`) to own them.
    | `models.mobility` / `models.institution`: your own models; they must extend the package's.
    */
    'connection' => env('ACADEMIC_MOBILITY_DB_CONNECTION'),
    'table_prefix' => 'academic_',
    'load_migrations' => true,

    'models' => [
        'mobility' => Mobility::class,
        'institution' => Institution::class,
        'requirement' => Requirement::class,
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
        'api_middleware' => ['api', 'auth'],
    ],

    'per_page' => 15,

    /*
    |--------------------------------------------------------------------------
    | Samples
    |--------------------------------------------------------------------------
    | Shows a "fill with random data" button in the visitor registration form (for
    | resolvers that can invent a person). Meant for demos and local development.
    */
    'samples' => env('ACADEMIC_MOBILITY_SAMPLES', false),

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
