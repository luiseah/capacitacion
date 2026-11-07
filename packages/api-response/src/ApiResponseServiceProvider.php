<?php

namespace Module\ApiResponse;

use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ServiceProvider;

class ApiResponseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ResponseFactory::mixin(new ResponseMixin);
    }
}
