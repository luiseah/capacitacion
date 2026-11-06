<?php

namespace App\Providers;

use App\Support\ResponseAPIMixin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

/**
 * Registers custom JSON response macros for API responses.
 *
 * This service provider adds three macros to the Response facade:
 * - api(): General API success responses with optional pagination.
 * - success(): Simple success response with message and extra data.
 * - error(): Error response with message and optional extra data.
 */
class ResponseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method is used to define response macros that will be
     * available throughout the application via the Response facade.
     *
     * @return void
     */
    public function boot(): void
    {
        Response::mixin(new ResponseAPIMixin);
    }
}
