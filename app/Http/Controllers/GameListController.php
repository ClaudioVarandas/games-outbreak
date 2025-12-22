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

        // User's own lists (private or public) - visible to logged-in user only
        $userLists = $user->gameLists()->userLists()->with('games')->get();

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

        return view('lists.index', compact('userLists', 'activeSystemLists', 'otherUsersLists'));
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
        }
        $data['is_public'] = $request->has('is_public') ? (bool)$request->input('is_public') : false;

        // Generate slug if creating system list and slug not provided
        // Also slugify the slug if provided (to ensure proper format)
        if ($data['is_system'] ?? false) {
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
        }

        // Ensure non-system lists don't have slug
        if (!($data['is_system'] ?? false)) {
            $data['slug'] = null;
            $data['is_active'] = false;
        }

        $gameList = GameList::create($data);

        return redirect()->route('lists.show', $gameList)
            ->with('success', 'List created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(GameList $gameList): View
    {
        // Check if user can view this list
        $user = auth()->user();
        if (!$gameList->is_public && (!$user || !$gameList->canBeEditedBy($user))) {
            abort(403);
        }

        $gameList->load(['games.platforms', 'games.genres', 'user']);
        $platformEnums = collect(PlatformEnum::cases())->keyBy(fn($e) => $e->value);

        return view('lists.show', compact('gameList', 'platformEnums'));
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
        $platformEnums = collect(PlatformEnum::cases())->keyBy(fn($e) => $e->value);

        return view('lists.edit', compact('gameList', 'canCreateSystem', 'platformEnums'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGameListRequest $request, GameList $gameList): RedirectResponse
    {
        $data = $request->validated();

        // Handle checkbox fields - if not present, set to false
        if ($request->user() && $request->user()->isAdmin()) {
            $data['is_system'] = $request->has('is_system') ? (bool)$request->input('is_system') : false;
            $data['is_active'] = $request->has('is_active') ? (bool)$request->input('is_active') : false;
        }
        $data['is_public'] = $request->has('is_public') ? (bool)$request->input('is_public') : false;

        // Generate slug if updating to system list and slug not provided
        if (($data['is_system'] ?? false) && empty($data['slug']) && !$gameList->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        }

        // Ensure non-system lists don't have slug
        if (!($data['is_system'] ?? false)) {
            $data['slug'] = null;
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
                    $query = "fields name, first_release_date, summary, platforms.name, cover.image_id,
                                 genres.name, genres.id, game_modes.name, game_modes.id,
                                 screenshots.image_id, videos.video_id,
                                 external_games.category, external_games.uid,
                                 websites.category, websites.url,
                                 similar_games.name, similar_games.cover.image_id, similar_games.id, game_type;
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

                    // Create game in database (same logic as GamesController::show)
                    $game = Game::create([
                        'igdb_id' => $igdbGame['id'],
                        'name' => $igdbGame['name'] ?? 'Unknown Game',
                        'summary' => $igdbGame['summary'] ?? null,
                        'first_release_date' => isset($igdbGame['first_release_date'])
                            ? \Carbon\Carbon::createFromTimestamp($igdbGame['first_release_date'])
                            : null,
                        'cover_image_id' => $igdbGame['cover']['image_id'] ?? null,
                        'game_type' => $igdbGame['game_type'] ?? 0,
                        'steam_data' => $igdbGame['steam'] ?? null,
                        'screenshots' => $igdbGame['screenshots'] ?? null,
                        'trailers' => $igdbGame['videos'] ?? null,
                        'similar_games' => $igdbGame['similar_games'] ?? null,
                    ]);

                    // Sync relations (platforms, genres, game modes)
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
        $gameList->games()->attach($game->id, ['order' => $maxOrder + 1]);

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
    public function removeGame(GameList $gameList, Game $game): RedirectResponse
    {
        if (!$gameList->canBeEditedBy(auth()->user())) {
            abort(403);
        }

        $gameList->games()->detach($game->id);

        return redirect()->back()->with('success', 'Game removed from list.');
    }

    /**
     * Display system list by slug (public interface).
     * Respects start_at and end_at dates for public access.
     */
    public function showBySlug(string $slug): View
    {
        $gameList = GameList::where('slug', $slug)
            ->where('is_system', true)
            ->where('is_active', true)
            ->where(function ($q) {
                // Check if list has started (start_at is null or <= now)
                $q->whereNull('start_at')
                  ->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                // Check if list hasn't ended (end_at is null or > now)
                $q->whereNull('end_at')
                  ->orWhere('end_at', '>', now());
            })
            ->firstOrFail();

        return $this->show($gameList);
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
