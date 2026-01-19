<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNewsFeatureEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $featureValue = config('features.news');

        // Completely disabled
        if ($featureValue === false || $featureValue === 'false') {
            abort(404);
        }

        // Admin preview mode - only admins can access
        if ($featureValue === 'admin') {
            if (! $request->user()?->isAdmin()) {
                abort(404);
            }
        }

        // true or 'true' - enabled for everyone
        return $next($request);
    }

    /**
     * Check if news feature is visible for navigation purposes.
     */
    public static function isVisibleTo(?object $user): bool
    {
        $featureValue = config('features.news');

        if ($featureValue === false || $featureValue === 'false') {
            return false;
        }

        if ($featureValue === 'admin') {
            return $user?->isAdmin() ?? false;
        }

        return true;
    }
}
