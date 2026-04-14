<?php

namespace App\Http\Middleware;

use App\Enums\NewsLocaleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetNewsLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $localePrefix = $this->resolveLocalePrefix($request);

        if ($localePrefix === null) {
            return $next($request);
        }

        $newsLocale = NewsLocaleEnum::fromPrefix($localePrefix);

        app()->setLocale($newsLocale->value);
        session(['news_locale' => $newsLocale->slugPrefix()]);
        view()->share('currentNewsLocale', $newsLocale);

        return $next($request);
    }

    private function resolveLocalePrefix(Request $request): ?string
    {
        $routePrefix = $request->route('localePrefix');

        if ($routePrefix !== null) {
            return $routePrefix;
        }

        if (str_starts_with($request->getPathInfo(), '/en/news')) {
            return 'en';
        }

        return null;
    }
}
