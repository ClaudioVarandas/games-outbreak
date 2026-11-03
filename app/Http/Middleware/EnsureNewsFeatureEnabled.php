<?php

namespace App\Http\Middleware;

use App\Enums\GlobalFeatureEnum;
use App\Enums\NewsVisibilityEnum;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNewsFeatureEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::isVisibleTo($request->user()), 404);

        return $next($request);
    }

    /**
     * Check if news feature is visible for navigation purposes.
     */
    public static function isVisibleTo(?User $user): bool
    {
        return NewsVisibilityEnum::current()->allows($user);
    }

    public static function homepageBlockIsVisibleTo(?User $user): bool
    {
        return self::isVisibleTo($user) && GlobalFeatureEnum::HomepageNews->value();
    }
}
