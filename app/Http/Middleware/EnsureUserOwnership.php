<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserOwnership
{
    /**
     * Handle an incoming request.
     *
     * Ensures that the authenticated user either owns the profile being accessed
     * or is an admin. Used for owner-only routes like create, edit, delete operations.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the user from the route parameter (resolved by route model binding)
        $user = $request->route('user');

        // If no user parameter, something is wrong with the route configuration
        if (!$user instanceof User) {
            abort(404);
        }

        // Check if the authenticated user owns this profile or is an admin
        if (auth()->check() && (auth()->id() === $user->id || auth()->user()->isAdmin())) {
            return $next($request);
        }

        // Not authorized
        abort(403, 'You do not have permission to manage this user\'s lists.');
    }
}
