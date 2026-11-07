<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => config('app.supported_locales'),
            // `lang/{locale}.json` (the app's and the packages'): English text => translation.
            'translations' => fn () => app('translator')->getLoader()->load(app()->getLocale(), '*', '*'),
            'auth' => [
                'user' => $request->user(),
                'roles' => fn () => $this->roles($request),
                'permissions' => fn () => $this->permissions($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function roles(Request $request): array
    {
        return $request->user()?->getRoleNames()->values()->all() ?? [];
    }

    /**
     * The permissions the front end can check. Admins pass every Gate check
     * (see AuthServiceProvider), so they receive every permission.
     *
     * @return array<int, string>
     */
    private function permissions(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        if ($user->hasRole(RoleName::Admin)) {
            return array_column(PermissionName::cases(), 'value');
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
