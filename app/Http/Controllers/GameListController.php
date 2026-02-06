<?php

namespace App\Http\Controllers;

use App\Enums\PlatformEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameListController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  bool  $readOnly  If true, hides all edit/add/remove functionality
     * @param  array  $initialFilters  Initial filter state from URL params
     */
    public function show(GameList $gameList, bool $readOnly = false, array $initialFilters = []): View
    {
        // Check if user can view this list
        $user = auth()->user();

        // Allow viewing if: user is owner/admin OR (list is public AND active)
        if (! $gameList->canBeEditedBy($user) && ! ($gameList->is_public && $gameList->is_active)) {
            abort(403);
        }

        if ($readOnly) {
            // For read-only (slug) views: order by release date (pivot or game)
            $games = $gameList->games()
                ->with(['platforms', 'genres', 'gameModes', 'playerPerspectives'])
                ->reorder() // Clear existing ordering from relationship
                ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
                ->orderBy('games.id', 'ASC') // Secondary sort for null dates
                ->get();

            // Assign to gameList for view compatibility
            $gameList->setRelation('games', $games);
            $gameList->load('user');
        } else {
            // For regular views: keep pivot order
            $gameList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives', 'user']);
        }

        $platformEnums = PlatformEnum::getActivePlatforms();

        // Prepare data for Alpine.js filtering
        $gamesData = $gameList->getGamesForFiltering();
        $filterOptions = $gameList->getFilterOptions();
        $computedSections = $gameList->getComputedSections();

        // Get user's backlog and wishlist for quick actions
        $backlogList = null;
        $wishlistList = null;
        $backlogGameIds = [];
        $wishlistGameIds = [];

        if (auth()->check()) {
            $backlogList = auth()->user()->gameLists()->backlog()->with('games:id')->first();
            $wishlistList = auth()->user()->gameLists()->wishlist()->with('games:id')->first();
            $backlogGameIds = $backlogList?->games->pluck('id')->toArray() ?? [];
            $wishlistGameIds = $wishlistList?->games->pluck('id')->toArray() ?? [];
        }

        // For events lists, group games by month (TBA first)
        $gamesByMonth = [];
        if ($gameList->isEvents()) {
            $gamesByMonth = $this->groupGamesByMonth($gameList);
        }

        return view('lists.show', compact(
            'gameList',
            'platformEnums',
            'readOnly',
            'gamesData',
            'filterOptions',
            'initialFilters',
            'computedSections',
            'backlogList',
            'wishlistList',
            'backlogGameIds',
            'wishlistGameIds',
            'gamesByMonth'
        ));
    }

    private function groupGamesByMonth(GameList $list): array
    {
        $gamesByMonth = [];

        foreach ($list->games as $game) {
            if ($game->pivot->is_tba) {
                $monthKey = 'tba';
                $monthLabel = 'To Be Announced';
                $monthNumber = null;
            } else {
                $releaseDate = $game->pivot->release_date ?? $game->first_release_date;
                if ($releaseDate && is_string($releaseDate)) {
                    $releaseDate = Carbon::parse($releaseDate);
                }

                if (! $releaseDate) {
                    $monthKey = 'tba';
                    $monthLabel = 'To Be Announced';
                    $monthNumber = null;
                } else {
                    $monthNumber = (int) $releaseDate->month;
                    $monthKey = $releaseDate->format('Y-m');
                    $monthLabel = $releaseDate->format('F Y');
                }
            }

            if (! isset($gamesByMonth[$monthKey])) {
                $gamesByMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'month_number' => $monthNumber ?? null,
                    'games' => [],
                ];
            }

            $gamesByMonth[$monthKey]['games'][] = $game;
        }

        // Sort: TBA first, then chronological
        uksort($gamesByMonth, function ($a, $b) {
            if ($a === 'tba') {
                return -1;
            }
            if ($b === 'tba') {
                return 1;
            }

            return $a <=> $b;
        });

        return $gamesByMonth;
    }

    /**
     * Display list by slug (public interface).
     * Shows visible lists OR if the authenticated user is the owner.
     * - Visible = is_public = true AND is_active = true
     * - Owner exception: Owner can always access their own list regardless of visibility
     * Note: Slug-based views are read-only (no add/remove games functionality)
     *
     * Admins: Can see all lists (no restrictions)
     * Owners: Can see their own lists (regardless of public/active status)
     * Authenticated non-owners: Can only see lists where is_public = true AND is_active = true
     * Guests: Can only see lists where is_public = true AND is_active = true
     */
    public function showBySlug(Request $request, string $type, string $slug): View
    {
        // Only allow system list types for this public route
        $allowedTypes = ['yearly', 'seasoned', 'events'];

        if (! in_array($type, $allowedTypes)) {
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

        // Parse URL query parameters for initial filter state
        $initialFilters = $this->parseFilterParams($request);

        // Slug-based views are read-only
        return $this->show($gameList, true, $initialFilters);
    }

    /**
     * Parse filter parameters from request
     */
    private function parseFilterParams(Request $request): array
    {
        $filters = [
            'platforms' => [],
            'genres' => [],
            'gameTypes' => [],
            'modes' => [],
            'perspectives' => [],
        ];

        // Parse comma-separated values from query params
        // Use strlen callback to preserve 0 values (array_filter removes them by default)
        $filterEmpty = fn ($value) => strlen($value) > 0;

        if ($request->has('platform')) {
            $filters['platforms'] = array_map('intval', array_filter(explode(',', $request->query('platform', '')), $filterEmpty));
        }

        if ($request->has('genre')) {
            $filters['genres'] = array_map('intval', array_filter(explode(',', $request->query('genre', '')), $filterEmpty));
        }

        if ($request->has('game_type')) {
            $filters['gameTypes'] = array_map('intval', array_filter(explode(',', $request->query('game_type', '')), $filterEmpty));
        }

        if ($request->has('mode')) {
            $filters['modes'] = array_map('intval', array_filter(explode(',', $request->query('mode', '')), $filterEmpty));
        }

        if ($request->has('perspective')) {
            $filters['perspectives'] = array_map('intval', array_filter(explode(',', $request->query('perspective', '')), $filterEmpty));
        }

        return $filters;
    }
}
