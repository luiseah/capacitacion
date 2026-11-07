<?php

namespace Module\Pagination;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Module\Pagination\Search\SearchFunction;

/**
 * Gives every database the `search(text)` function (lower case, no accents) that
 * {@see PaginatedBuilder::whereSearch()} uses: created by a migration on PostgreSQL,
 * registered from PHP on SQLite (tests).
 */
class PaginationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => SearchFunction::register($event->connection));
    }
}
