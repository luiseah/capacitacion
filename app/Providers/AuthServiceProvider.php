<?php

namespace App\Providers;

use App\Enums\RoleName;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function boot()
    {
        // Implicitly grant the admin role every permission (`$user->can('users.index')`, `@can`).
        // Checks against a model or class (`Gate::authorize('delete', $user)`) are left to
        // their policy, so its own rules (e.g. nobody deletes themselves) apply to admins too.
        Gate::before(function ($user, $ability, array $arguments) {
            return $arguments === [] && $user->hasRole(RoleName::Admin) ? true : null;
        });

    }
}
