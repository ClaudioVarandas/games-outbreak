<?php

namespace App\Http\Controllers;

use App\Enums\PlatformEnum;
use App\Http\Requests\StoreGameListRequest;
use App\Http\Requests\UpdateGameListRequest;
use App\Models\Game;
use App\Models\GameList;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class GameListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $user = auth()->user();

        // Ensure special lists exist (in case they were deleted somehow)
        $user->ensureSpecialLists();

        // Separate regular lists from special lists
        $regularLists = $user->gameLists()
            ->userLists()
            ->regular()
            ->with('games')
            ->get();

        $backlogList = $user->gameLists()
            ->backlog()
            ->with('games')
            ->first();

        $wishlistList = $user->gameLists()
            ->wishlist()
            ->with('games')
            ->first();

        // System lists - visible to admin users only
        // Note: is_active, is_public, start_at, and end_at are for public interface only
        // Admins see ALL system lists regardless of status or dates
        $activeSystemLists = collect();
        if ($user->isAdmin()) {
            $activeSystemLists = GameList::where('is_system', true)
                ->with('games')
                ->get();
        }

        // Other users' lists - visible to admin users only
        // Note: is_active, is_public, start_at, and end_at are for public interface only
        // Admins see ALL other users' lists regardless of status or dates
        $otherUsersLists = collect();
        if ($user->isAdmin()) {
            $otherUsersLists = GameList::where('is_system', false)
                ->where('user_id', '!=', $user->id)
                ->whereNotNull('user_id')
                ->with(['games', 'user'])
                ->latest()
                ->get();
        }

        return view('lists.index', compact('regularLists', 'backlogList', 'wishlistList', 'activeSystemLists', 'otherUsersLists'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $canCreateSystem = auth()->user()->canCreateSystemLists();
        return view('lists.create', compact('canCreateSystem'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGameListRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // Handle checkbox fields - if not present, set to false
        if ($request->user() && $request->user()->isAdmin()) {
            $data['is_system'] = $request->has('is_system') ? (bool)$request->input('is_system') : false;
            $data['is_active'] = $request->has('is_active') ? (bool)$request->input('is_active') : false;

            // If creating a system list, use the provided list_type (seasoned or monthly)
            // Otherwise, set to 'regular' for user-created lists
            if ($data['is_system'] && !empty($request->input('list_type'))) {
                $data['list_type'] = $request->input('list_type');
            } else {
                $data['list_type'] = \App\Enums\ListTypeEnum::REGULAR->value;
            }
        } else {
            // Set list_type to 'regular' for user-created lists
            $data['list_type'] = \App\Enums\ListTypeEnum::REGULAR->value;
        }

        $data['is_public'] = $request->has('is_public') ? (bool)$request->input('is_public') : false;

        // Handle slug generation for all lists (mandatory)
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        } else {
            // Slugify the provided slug to ensure proper format
            $data['slug'] = Str::slug($data['slug']);
            // Check uniqueness and append number if needed
            $originalSlug = $data['slug'];
            $counter = 1;
            while (GameList::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Set is_active for non-system lists
        if (!($data['is_system'] ?? false)) {
            $data['is_active'] = false;
        }

        $gameList = GameList::create($data);

        return redirect()->route('lists.show', $gameList)
            ->with('success', 'List created successfully.');
    }

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
     * Show the form for editing the specified resource.
     */
    public function edit(GameList $gameList): View
    {
        $user = auth()->user();
        if (!$user || !$gameList->canBeEditedBy($user)) {
            abort(403);
        }

        $gameList->load(['games.platforms', 'games.genres']);
        $canCreateSystem = $user->canCreateSystemLists();
        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('lists.edit', compact('gameList', 'canCreateSystem', 'platformEnums'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGameListRequest $request, GameList $gameList): RedirectResponse
    {
        $data = $request->validated();

        // Prevent renaming backlog/wishlist lists
        if ($gameList->isSpecialList() && isset($data['name']) && $data['name'] !== $gameList->name) {
            abort(403, 'Backlog and wishlist lists cannot be renamed.');
        }

        // Handle checkbox fields - if not present, set to false
        if ($request->user() && $request->user()->isAdmin()) {
            $data['is_system'] = $request->has('is_system') ? (bool)$request->input('is_system') : false;
            $data['is_active'] = $request->has('is_active') ? (bool)$request->input('is_active') : false;

            // Allow admins to change list_type for system lists
            if ($data['is_system'] && !empty($request->input('list_type'))) {
                $data['list_type'] = $request->input('list_type');
            }
        }
        $data['is_public'] = $request->has('is_public') ? (bool)$request->input('is_public') : false;

        // Handle slug generation for all lists (mandatory)
        if (empty($data['slug'])) {
            // Generate slug if not provided and list doesn't have one
            if (!$gameList->slug) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            }
            // If list already has a slug and none provided, keep existing slug (don't overwrite)
        } else {
            // Slugify and ensure uniqueness if slug is provided
            $data['slug'] = Str::slug($data['slug']);
            $originalSlug = $data['slug'];
            $counter = 1;
            while (GameList::where('slug', $data['slug'])->where('id', '!=', $gameList->id)->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Set is_active for non-system lists
        if (!($data['is_system'] ?? false)) {
            $data['is_active'] = false;
        }

        $gameList->update($data);

        return redirect()->route('lists.show', $gameList)
            ->with('success', 'List updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameList $gameList): RedirectResponse
    {
        if (!$gameList->canBeEditedBy(auth()->user())) {
            abort(403);
        }

        // Prevent deletion of backlog/wishlist lists
        if (!$gameList->canBeDeleted()) {
            abort(403, 'Backlog and wishlist lists cannot be deleted.');
        }

        $gameList->delete();

        return redirect()->route('lists.index')
            ->with('success', 'List deleted successfully.');
    }

    /**
     * Add a game to the list.
     */
    public function addGame(Request $request, GameList $gameList): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if (!$gameList->canBeEditedBy(auth()->user())) {
            abort(403);
        }

        $request->validate([
            'game_id' => ['required'],
        ]);

        $igdbId = $request->game_id;

        // First, try to find existing game by IGDB ID (search always returns IGDB IDs)
        $game = Game::where('igdb_id', $igdbId)->first();

        // If game doesn't exist in DB, fetch it from IGDB (same logic as game show page)
        if (!$game) {
            // Also check if it's a database ID (for backward compatibility)
            if (is_numeric($igdbId) && strlen($igdbId) < 10) {
                $game = Game::find($igdbId);
            }

            // If still not found, fetch from IGDB
            if (!$game) {
                try {
                    $igdbService = app(\App\Services\IgdbService::class);
                    $query = "fields name, first_release_date, summary, platforms.name, platforms.id, cover.image_id,
                             genres.name, genres.id,
                             game_modes.name, game_modes.id,
                             similar_games.name, similar_games.cover.image_id, similar_games.id,
                             screenshots.image_id,
                             videos.video_id,
                             external_games.category, external_games.uid,
                             websites.category, websites.url, game_type,
                             release_dates.platform, release_dates.date, release_dates.region, release_dates.human, release_dates.y, release_dates.m, release_dates.d, release_dates.status,
                             involved_companies.company.id, involved_companies.company.name, involved_companies.developer, involved_companies.publisher,
                             game_engines.name, game_engines.id,
                             player_perspectives.name, player_perspectives.id;
                         where id = {$igdbId}; limit 1;";

                    $response = \Http::igdb()
                        ->withBody($query, 'text/plain')
                        ->post('https://api.igdb.com/v4/games');

                    if ($response->failed() || empty($response->json())) {
                        if ($request->wantsJson() || $request->ajax()) {
                            return response()->json(['error' => 'Game not found in IGDB.'], 404);
                        }
                        return redirect()->back()->with('error', 'Game not found in IGDB.');
                    }

                    $igdbGame = $response->json()[0];

                    // Enrich with Steam data
                    $igdbGame = $igdbService->enrichWithSteamData([$igdbGame])[0] ?? $igdbGame;

                    $gameName = $igdbGame['name'] ?? 'Unknown Game';
                    $steamAppId = $igdbGame['steam']['appid'] ?? null;
                    $igdbGameId = $igdbGame['id'] ?? null;

                    // Store IGDB cover.image_id in cover_image_id
                    $coverImageId = $igdbGame['cover']['image_id'] ?? null;

                    // For hero: Use IGDB cover if available
                    $heroImageId = $igdbGame['cover']['image_id'] ?? null;

                    // Logo will be fetched asynchronously (always null initially)
                    $logoImageId = null;

                    // Determine which images need to be fetched from SteamGridDB
                    $imagesToFetch = [];
                    if (!$coverImageId) {
                        $imagesToFetch[] = 'cover';
                    }
                    if (!$heroImageId) {
                        $imagesToFetch[] = 'hero';
                    }
                    // Logo is always fetched (not provided by IGDB)
                    $imagesToFetch[] = 'logo';

                    // Create game in database
                    $game = Game::create([
                        'igdb_id' => $igdbGame['id'],
                        'name' => $gameName,
                        'summary' => $igdbGame['summary'] ?? null,
                        'first_release_date' => isset($igdbGame['first_release_date'])
                            ? \Carbon\Carbon::createFromTimestamp($igdbGame['first_release_date'])
                            : null,
                        'cover_image_id' => $coverImageId,
                        'hero_image_id' => $heroImageId,
                        'logo_image_id' => $logoImageId,
                        'game_type' => $igdbGame['game_type'] ?? 0,
                        'steam_data' => $igdbGame['steam'] ?? null,
                        'screenshots' => $igdbGame['screenshots'] ?? null,
                        'trailers' => $igdbGame['videos'] ?? null,
                        'similar_games' => $igdbGame['similar_games'] ?? null,
                    ]);

                    // Sync relations (platforms, genres, game modes) and release dates
                    Game::syncReleaseDates($game, $igdbGame['release_dates'] ?? null);
                    $this->syncRelations($game, $igdbGame);
                } catch (\Exception $e) {
                    \Log::error("Failed to fetch game from IGDB", ['igdb_id' => $igdbId, 'error' => $e->getMessage()]);
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json(['error' => 'Failed to fetch game from IGDB. Please try again.'], 500);
                    }
                    return redirect()->back()->with('error', 'Failed to fetch game from IGDB. Please try again.');
                }
            }
        }

        if (!$game) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Game not found.'], 404);
            }
            return redirect()->back()->with('error', 'Game not found.');
        }

        // Check if game is already in list
        if ($gameList->games()->where('game_id', $game->id)->exists()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['info' => 'Game is already in this list.'], 200);
            }
            return redirect()->back()->with('info', 'Game is already in this list.');
        }

        // Add game to list
        $maxOrder = $gameList->games()->max('order') ?? 0;

        // Get release_date from request or default to game's first_release_date
        $releaseDate = $request->input('release_date');
        if ($releaseDate) {
            try {
                $releaseDate = \Carbon\Carbon::parse($releaseDate);
            } catch (\Exception $e) {
                $releaseDate = $game->first_release_date;
            }
        } else {
            $releaseDate = $game->first_release_date;
        }

        // Get platforms from request or default to game's platforms
        $platformIds = $request->input('platforms', []);
        if (is_string($platformIds)) {
            $platformIds = json_decode($platformIds, true) ?? [];
        }
        if (empty($platformIds)) {
            // Default to game's platforms (IGDB IDs)
            $game->load('platforms');
            $platformIds = $game->platforms
                ->filter(fn($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                ->map(fn($p) => $p->igdb_id)
                ->values()
                ->toArray();
        }

        $gameList->games()->attach($game->id, [
            'order' => $maxOrder + 1,
            'release_date' => $releaseDate,
            'platforms' => json_encode($platformIds),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Game added to list.',
                'game' => [
                    'id' => $game->id,
                    'name' => $game->name,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Game added to list.');
    }

    /**
     * Remove a game from the list.
     */
    public function removeGame(Request $request, GameList $gameList, Game $game): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if (!$gameList->canBeEditedBy(auth()->user())) {
            abort(403);
        }

        $gameList->games()->detach($game->id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Game removed from list.',
                'game' => [
                    'id' => $game->id,
                    'name' => $game->name,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Game removed from list.');
    }

    /**
     * Display backlog page with grid/list view.
     */
    public function backlog(Request $request): View
    {
        $user = auth()->user();
        $user->ensureSpecialLists();

        $backlogList = $user->gameLists()
            ->backlog()
            ->with('games')
            ->first();

        $viewMode = $request->query('view', 'grid'); // 'grid' or 'list'
        $page = (int) $request->query('page', 1);
        $perPage = 25;

        $games = collect();
        $totalResults = 0;
        $totalPages = 1;
        $hasMore = false;

        if ($backlogList) {
            $allGames = $backlogList->games;
            $totalResults = $allGames->count();
            $totalPages = (int) ceil($totalResults / $perPage);

            $games = $allGames->skip(($page - 1) * $perPage)->take($perPage);
            $hasMore = $page < $totalPages;
        }

        $platformEnums = PlatformEnum::getActivePlatforms();

        $currentPage = $page;

        return view('backlog.index', compact(
            'games',
            'viewMode',
            'totalResults',
            'currentPage',
            'totalPages',
            'hasMore',
            'platformEnums'
        ));
    }

    /**
     * Display wishlist page with grid/list view.
     */
    public function wishlist(Request $request): View
    {
        $user = auth()->user();
        $user->ensureSpecialLists();

        $wishlistList = $user->gameLists()
            ->wishlist()
            ->with('games')
            ->first();

        $viewMode = $request->query('view', 'grid'); // 'grid' or 'list'
        $page = (int) $request->query('page', 1);
        $perPage = 25;

        $games = collect();
        $totalResults = 0;
        $totalPages = 1;
        $hasMore = false;

        if ($wishlistList) {
            $allGames = $wishlistList->games;
            $totalResults = $allGames->count();
            $totalPages = (int) ceil($totalResults / $perPage);

            $games = $allGames->skip(($page - 1) * $perPage)->take($perPage);
            $hasMore = $page < $totalPages;
        }

        $platformEnums = PlatformEnum::getActivePlatforms();

        $currentPage = $page;

        return view('wishlist.index', compact(
            'games',
            'viewMode',
            'totalResults',
            'currentPage',
            'totalPages',
            'hasMore',
            'platformEnums'
        ));
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
    public function showBySlug(string $slug): View
    {
        $user = auth()->user();

        $gameList = GameList::where('slug', $slug)
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

    /**
     * Display all system lists (admin only).
     */
    public function systemIndex(): View
    {
        $systemLists = GameList::system()->with(['user', 'games'])->orderBy('created_at', 'desc')->get();
        return view('admin.system-lists.index', compact('systemLists'));
    }

    /**
     * Show the form for creating a new system list.
     */
    public function createSystem(): View
    {
        return view('admin.system-lists.create');
    }

    /**
     * Store a newly created system list.
     */
    public function storeSystem(StoreGameListRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['is_system'] = true;

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        }

        $gameList = GameList::create($data);

        return redirect()->route('admin.system-lists.index')
            ->with('success', 'System list created successfully.');
    }

    /**
     * Toggle active status of system list.
     */
    public function toggleActive(GameList $gameList): RedirectResponse
    {
        $gameList->update(['is_active' => !$gameList->is_active]);

        return redirect()->back()
            ->with('success', 'List status updated.');
    }

    /**
     * Sync game relations (platforms, genres, game modes) from IGDB data.
     */
    private function syncRelations(Game $game, array $igdbGame): void
    {
        if (!empty($igdbGame['platforms'])) {
            $ids = collect($igdbGame['platforms'])->map(fn($p) =>
                Platform::firstOrCreate(['igdb_id' => $p['id']], ['name' => $p['name'] ?? 'Unknown'])->id
            );
            $game->platforms()->sync($ids);
        }

        if (!empty($igdbGame['genres'])) {
            $ids = collect($igdbGame['genres'])->map(fn($g) =>
                Genre::firstOrCreate(['igdb_id' => $g['id']], ['name' => $g['name'] ?? 'Unknown'])->id
            );
            $game->genres()->sync($ids);
        }

        if (!empty($igdbGame['game_modes'])) {
            $ids = collect($igdbGame['game_modes'])->map(fn($m) =>
                GameMode::firstOrCreate(['igdb_id' => $m['id']], ['name' => $m['name'] ?? 'Unknown'])->id
            );
            $game->gameModes()->sync($ids);
        }
    }

    /**
     * Generate a unique slug from name.
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
