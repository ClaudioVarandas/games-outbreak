<?php

namespace App\Http\Middleware;

use App\Enums\NewsLocaleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = session('locale');

        $locale = $slug
            ? NewsLocaleEnum::fromPrefix($slug)->value
            : NewsLocaleEnum::fromBrowserLocale($request->header('Accept-Language'))->value;

        app()->setLocale($locale);

        return $next($request);
    }
}
