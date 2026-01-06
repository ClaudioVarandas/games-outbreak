<?php

namespace App\Http\Controllers;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Http\Requests\StoreGameListRequest;
use App\Http\Requests\UpdateGameListRequest;
use App\Http\Requests\UpdateRegularListRequest;
use App\Models\Game;
use App\Models\GameList;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserListController extends Controller
{
    /**
     * Check if the authenticated user can manage the given user's lists.
     */
    protected function canManage(User $user): bool
    {
        return auth()->check() && (
            auth()->id() === $user->id ||
            auth()->user()->isAdmin()
        );
    }

    /**
     * Display user's backlog (dual-mode: view for public, management for owner).
     */
    public function backlog(User $user): View
    {
        $user->ensureSpecialLists();
        $list = $user->gameLists()
            ->backlog()
            ->with(['games' => function ($query) {
                $query->orderByPivot('order');
            }])
            ->firstOrFail();

        $canManage = $this->canManage($user);
        $viewMode = session('game_view_mode', 'grid');

        return view('user-lists.backlog', compact('user', 'list', 'canManage', 'viewMode'));
    }

    /**
     * Display user's wishlist (dual-mode: view for public, management for owner).
     */
    public function wishlist(User $user): View
    {
        $user->ensureSpecialLists();
        $list = $user->gameLists()
            ->wishlist()
            ->with(['games' => function ($query) {
                $query->orderByPivot('order');
            }])
            ->firstOrFail();

        $canManage = $this->canManage($user);
        $viewMode = session('game_view_mode', 'grid');

        return view('user-lists.wishlist', compact('user', 'list', 'canManage', 'viewMode'));
    }

    /**
     * Display user's regular lists (dual-mode: view for public, management for owner).
     */
    public function regularLists(User $user): View
    {
        $lists = $user->gameLists()
            ->regular()
            ->with(['games' => function ($query) {
                $query->orderByPivot('order');
            }])
            ->get();

        $canManage = $this->canManage($user);
        $viewMode = session('game_view_mode', 'grid');

        return view('user-lists.regular.index', compact('user', 'lists', 'canManage', 'viewMode'));
    }

    /**
     * Display user's regular lists overview.
     * Dual-mode: view for public, management for owner.
     */
    public function myLists(User $user): View
    {
        $regularLists = $user->gameLists()
            ->regular()
            ->with(['games' => function ($query) {
                $query->orderByPivot('order');
            }])
            ->get();

        $canManage = $this->canManage($user);

        return view('user-lists.my-lists', compact(
            'user',
            'regularLists',
            'canManage'
        ));
    }

    /**
     * Show the form for creating a new regular list (owner-only).
     */
    public function createRegular(User $user): View
    {
        return view('user-lists.regular.create', compact('user'));
    }

    /**
     * Store a newly created regular list (owner-only).
     */
    public function storeRegular(StoreGameListRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $user->id;
        $data['list_type'] = ListTypeEnum::REGULAR->value;
        $data['is_system'] = false;
        $data['is_active'] = false;
        $data['is_public'] = $request->has('is_public') ? (bool)$request->input('is_public') : false;

        // Generate unique slug
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], ListTypeEnum::REGULAR->value);
        } else {
            $data['slug'] = Str::slug($data['slug']);
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], ListTypeEnum::REGULAR->value);
        }

        $list = GameList::create($data);

        return redirect()->route('user.lists.regular.edit', [$user->username, $list->slug])
            ->with('success', 'List created successfully.');
    }

    /**
     * Show the form for editing a regular list (owner-only).
     */
    public function editRegular(User $user, string $slug): View
    {
        $list = $user->gameLists()
            ->where('slug', $slug)
            ->where('list_type', ListTypeEnum::REGULAR->value)
            ->with(['games' => function ($query) {
                $query->orderByPivot('order');
            }])
            ->firstOrFail();

        $viewMode = session('game_view_mode', 'grid');

        return view('user-lists.regular.edit', compact('user', 'list', 'viewMode'));
    }

    /**
     * Update a regular list (owner-only).
     */
    public function updateRegular(UpdateRegularListRequest $request, User $user, string $slug): RedirectResponse
    {
        $list = $user->gameLists()
            ->where('slug', $slug)
            ->where('list_type', ListTypeEnum::REGULAR->value)
            ->firstOrFail();

        $data = $request->validated();
        $data['is_public'] = $request->has('is_public') ? (bool)$request->input('is_public') : false;

        // Handle slug changes
        if (isset($data['slug']) && $data['slug'] !== $list->slug) {
            $data['slug'] = Str::slug($data['slug']);
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], ListTypeEnum::REGULAR->value, $list->id);
        }

        $list->update($data);

        // Redirect to new slug if changed
        $newSlug = $data['slug'] ?? $list->slug;

        return redirect()->route('user.lists.regular.edit', [$user->username, $newSlug])
            ->with('success', 'List updated successfully.');
    }

    /**
     * Delete a regular list (owner-only).
     */
    public function destroyRegular(User $user, string $slug): RedirectResponse
    {
        $list = $user->gameLists()
            ->where('slug', $slug)
            ->where('list_type', ListTypeEnum::REGULAR->value)
            ->firstOrFail();

        $list->delete();

        return redirect()->route('user.lists.my-lists', [$user->username])
            ->with('success', 'List deleted successfully.');
    }

    /**
     * Add a game to a list (owner-only).
     */
    public function addGame(Request $request, User $user, string $type): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $list = $this->getListByType($user, $type);

        $request->validate([
            'game_id' => ['required'],
        ]);

        $igdbId = $request->game_id;

        // Find or fetch game from IGDB
        $game = Game::where('igdb_id', $igdbId)->first();

        if (!$game) {
            // Try by database ID
            if (is_numeric($igdbId) && strlen($igdbId) < 10) {
                $game = Game::find($igdbId);
            }

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

        // Check if already in list
        if ($list->games->contains($game->id)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['info' => 'Game is already in this list.']);
            }
            return redirect()->back()->with('info', 'Game is already in this list.');
        }

        // Get max order
        $maxOrder = $list->games()->max('order') ?? 0;

        // Get release date and platforms from request or defaults
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

        $platformIds = $request->input('platforms', []);
        if (is_string($platformIds)) {
            $platformIds = json_decode($platformIds, true) ?? [];
        }
        if (empty($platformIds)) {
            $game->load('platforms');
            $platformIds = $game->platforms
                ->filter(fn($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                ->map(fn($p) => $p->igdb_id)
                ->values()
                ->toArray();
        }

        $list->games()->attach($game->id, [
            'order' => $maxOrder + 1,
            'release_date' => $releaseDate,
            'platforms' => json_encode($platformIds),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Game added to list.',
            ]);
        }

        return redirect()->back()->with('success', 'Game added to list.');
    }

    /**
     * Remove a game from a list (owner-only).
     */
    public function removeGame(User $user, string $type, Game $game): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $list = $this->getListByType($user, $type);

        $list->games()->detach($game->id);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Game removed from list.',
            ]);
        }

        return redirect()->back()->with('success', 'Game removed from list.');
    }

    /**
     * Reorder games in a list (owner-only).
     */
    public function reorderGames(Request $request, User $user, string $type): \Illuminate\Http\JsonResponse
    {
        $list = $this->getListByType($user, $type);

        $request->validate([
            'game_ids' => ['required', 'array'],
            'game_ids.*' => ['required', 'integer'],
        ]);

        $gameIds = $request->input('game_ids');

        foreach ($gameIds as $index => $gameId) {
            $list->games()->updateExistingPivot($gameId, ['order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Games reordered successfully.',
        ]);
    }

    /**
     * Toggle view mode (grid/list) in session.
     */
    public function toggleViewMode(Request $request): \Illuminate\Http\JsonResponse
    {
        $mode = $request->input('mode', 'grid');
        session(['game_view_mode' => $mode]);

        return response()->json(['success' => true, 'mode' => $mode]);
    }

    /**
     * Helper: Get list by type (backlog, wishlist, or regular slug).
     */
    protected function getListByType(User $user, string $type): GameList
    {
        if ($type === 'backlog') {
            return $user->gameLists()->backlog()->firstOrFail();
        }

        if ($type === 'wishlist') {
            return $user->gameLists()->wishlist()->firstOrFail();
        }

        // For regular lists, $type is the slug
        return $user->gameLists()
            ->where('slug', $type)
            ->where('list_type', ListTypeEnum::REGULAR->value)
            ->firstOrFail();
    }

    /**
     * Helper: Generate unique slug.
     */
    protected function generateUniqueSlug(string $name, string $listType, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        return $this->ensureUniqueSlug($slug, $listType, $excludeId);
    }

    /**
     * Helper: Ensure slug is unique within list type.
     */
    protected function ensureUniqueSlug(string $slug, string $listType, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        $query = GameList::where('slug', $slug)->where('list_type', $listType);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
            $query = GameList::where('slug', $slug)->where('list_type', $listType);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
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
}
