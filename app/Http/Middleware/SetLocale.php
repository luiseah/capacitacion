<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the language of the request: the `locale` cookie the language switcher sets, else
 * the browser's `Accept-Language`, else the app's default. Only supported locales are accepted.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', [config('app.locale')]);

        $locale = $request->cookie('locale');

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported) ?? config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
