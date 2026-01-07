<?php

namespace App\Http\Controllers;

use App\Enums\PlatformEnum;
use App\Models\GameList;
use Illuminate\View\View;

class GameListController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param bool $readOnly If true, hides all edit/add/remove functionality
     */
    public function show(GameList $gameList, bool $readOnly = false): View
    {
        // Check if user can view this list
        $user = auth()->user();

        // Allow viewing if: user is owner/admin OR (list is public AND active)
        if (!$gameList->canBeEditedBy($user) && !($gameList->is_public && $gameList->is_active)) {
            abort(403);
        }

        if ($readOnly) {
            // For read-only (slug) views: order by release date (pivot or game)
            $games = $gameList->games()
                ->with(['platforms', 'genres'])
                ->reorder() // Clear existing ordering from relationship
                ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
                ->orderBy('games.id', 'ASC') // Secondary sort for null dates
                ->get();

            // Assign to gameList for view compatibility
            $gameList->setRelation('games', $games);
            $gameList->load('user');
        } else {
            // For regular views: keep pivot order
            $gameList->load(['games.platforms', 'games.genres', 'user']);
        }

        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('lists.show', compact('gameList', 'platformEnums', 'readOnly'));
    }

    /**
     * Display list by slug (public interface).
     * Shows visible lists OR if the authenticated user is the owner.
     * - Visible = is_public = true AND is_active = true
     * - Owner exception: Owner can always access their own list regardless of visibility
     * Note: Slug-based views are read-only (no add/remove games functionality)
     *
     * ✅ Admins: Can see all lists (no restrictions)
     * ✅ Owners: Can see their own lists (regardless of public/active status)
     * ✅ Authenticated non-owners: Can only see lists where is_public = true AND is_active = true
     * ✅ Guests: Can only see lists where is_public = true AND is_active = true
     */
    public function showBySlug(string $type, string $slug): View
    {
        // Only allow system list types for this public route
        $allowedTypes = ['monthly', 'indie', 'seasoned'];

        if (!in_array($type, $allowedTypes)) {
            abort(404, 'List type not found. User lists are available at /u/{username}/lists/{slug}');
        }

        // Validate and convert type slug to enum
        $listType = \App\Enums\ListTypeEnum::fromSlug($type);
        if ($listType === null) {
            abort(404, 'Invalid list type');
        }

        $user = auth()->user();

        $gameList = GameList::where('slug', $slug)
            ->where('list_type', $listType->value)
            ->whereNotNull('slug')
            ->where(function ($query) use ($user) {
                if ($user) {
                    // Admins can see all lists
                    if ($user->is_admin) {
                        // No additional conditions - admins see everything
                        return;
                    }

                    // Owner can always see their own list (even if inactive/private)
                    $query->where('user_id', $user->id)
                        ->orWhere(function ($q) {
                            // Non-owners see lists that are BOTH public AND active
                            $q->where('is_public', true)
                                ->where('is_active', true);
                        });
                } else {
                    // Non-authenticated users (guests) see lists that are BOTH public AND active
                    $query->where('is_public', true)
                        ->where('is_active', true);
                }
            })
            ->firstOrFail();

        // Slug-based views are read-only
        return $this->show($gameList, true);
    }
}
