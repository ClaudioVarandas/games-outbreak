<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureImportToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('services.import.token');

        if (! is_string($configuredToken) || $configuredToken === '') {
            return response()->json(['error' => 'Import API is not configured.'], 503);
        }

        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || ! hash_equals($configuredToken, $bearerToken)) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
