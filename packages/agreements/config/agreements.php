<?php

use Module\Agreements\Models\Agreement;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    | Your own agreement model; it must extend the package's. Tables, connection
    | and the institution model come from the `academic-mobility` config.
    */
    'models' => [
        'agreement' => Agreement::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    | JSON API + Inertia pages. Middleware replaces the defaults, so list the auth
    | you need (e.g. `['api', 'auth:sanctum']` for the API of a Sanctum SPA).
    */
    'routes' => [
        'enabled' => true,
        'web_prefix' => 'agreements',
        'api_prefix' => 'api/agreements',
        'web_middleware' => ['web', 'auth'],
        'api_middleware' => ['api', 'auth'],
    ],

    'per_page' => 15,

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    | Disk where the signed documents are kept (null = the application's default
    | disk). They are served through the API, never publicly.
    */
    'documents' => [
        'disk' => env('AGREEMENTS_DOCUMENTS_DISK'),
    ],
];
