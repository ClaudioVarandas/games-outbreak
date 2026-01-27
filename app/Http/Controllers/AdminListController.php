<?php

namespace App\Http\Controllers;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Enums\PlatformGroupEnum;
use App\Http\Requests\StoreGameListRequest;
use App\Http\Requests\UpdateGameListRequest;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\IgdbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminListController extends Controller
{
    /**
     * Redirect to unified user lists page.
     */
    public function myLists(): RedirectResponse
    {
        return redirect()->route('user.lists.lists', ['user' => auth()->user()->username], 301);
    }

    /**
     * Display all system lists (grouped by monthly/indie/seasoned).
     */
    public function systemLists(Request $request): View
    {
        // Get all monthly lists (ignore is_active/is_public), group by year
        $monthlyLists = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::MONTHLY)
            ->with('games')
            ->orderByDesc('start_at')
            ->get()
            ->groupBy(function ($list) {
                return $list->start_at ? $list->start_at->year : 'Unknown';
            });

        // Get all indie games lists (ignore is_active/is_public), group by year
        $indieGamesList = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::INDIE_GAMES)
            ->with('games')
            ->orderByDesc('start_at')
            ->get()
            ->groupBy(function ($list) {
                return $list->start_at ? $list->start_at->year : 'Unknown';
            });

        // Get only active seasoned lists (no grouping)
        $seasonedLists = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::SEASONED)
            ->where('is_active', true)
            ->with('games')
            ->orderBy('name')
            ->get();

        // Get all events lists (flat list, ordered by created_at desc)
        $eventsLists = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::EVENTS)
            ->with('games')
            ->orderByDesc('created_at')
            ->get();

        // Get all highlights lists (similar to seasoned - only active, no grouping)
        $highlightsLists = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::HIGHLIGHTS)
            ->where('is_active', true)
            ->with('games')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.system-lists.index', compact(
            'monthlyLists',
            'indieGamesList',
            'seasonedLists',
            'eventsLists',
            'highlightsLists'
        ));
    }

    /**
     * Display all users' lists (sorted by username).
     */
    public function userLists(Request $request): View
    {
        $query = GameList::with(['user', 'games'])
            ->whereNotNull('user_id')
            ->where('is_system', false);

        // Apply filters
        if ($request->filled('username')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('username', 'like', '%'.$request->username.'%');
            });
        }

        if ($request->filled('list_type')) {
            $listType = ListTypeEnum::fromSlug($request->list_type);
            if ($listType) {
                $query->where('list_type', $listType->value);
            }
        }

        if ($request->filled('visibility')) {
            $query->where('is_public', $request->visibility === 'public');
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Order by username, then list type
        $lists = $query->get()
            ->sortBy(function ($list) {
                return $list->user->username ?? '';
            })
            ->groupBy(function ($list) {
                return $list->user_id;
            });

        return view('admin.user-lists', compact('lists'));
    }

    /**
     * Show the form for creating a new system list.
     */
    public function createSystemList(): View
    {
        return view('admin.system-lists.create');
    }

    /**
     * Store a newly created system list.
     */
    public function storeSystemList(StoreGameListRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['is_system'] = true;
        $data['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;
        $data['is_public'] = $request->has('is_public') ? (bool) $request->input('is_public') : false;

        // Validate list_type for system lists
        if (! in_array($data['list_type'], [
            ListTypeEnum::MONTHLY->value,
            ListTypeEnum::INDIE_GAMES->value,
            ListTypeEnum::SEASONED->value,
            ListTypeEnum::EVENTS->value,
            ListTypeEnum::HIGHLIGHTS->value,
        ])) {
            return redirect()->back()
                ->withErrors(['list_type' => 'Invalid list type for system lists.'])
                ->withInput();
        }

        // Generate unique slug
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $data['list_type']);
        } else {
            $data['slug'] = Str::slug($data['slug']);
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $data['list_type']);
        }

        $list = GameList::create($data);

        $listType = ListTypeEnum::from($data['list_type']);

        return redirect()->route('admin.system-lists.edit', [$listType->toSlug(), $list->slug])
            ->with('success', 'System list created successfully.');
    }

    /**
     * Show the form for editing a system list.
     */
    public function editSystemList(string $type, string $slug): View
    {
        $listType = ListTypeEnum::fromSlug($type);
        if ($listType === null) {
            abort(404, 'Invalid list type');
        }

        $list = GameList::where('slug', $slug)
            ->where('list_type', $listType->value)
            ->where('is_system', true)
            ->with(['games' => function ($query) {
                $query->orderByPivot('order');
            }])
            ->firstOrFail();

        $viewMode = session('game_view_mode', 'grid');

        return view('admin.system-lists.edit', compact('list', 'viewMode'));
    }

    /**
     * Update a system list.
     */
    public function updateSystemList(UpdateGameListRequest $request, string $type, string $slug): RedirectResponse
    {
        $listType = ListTypeEnum::fromSlug($type);
        if ($listType === null) {
            abort(404, 'Invalid list type');
        }

        $list = GameList::where('slug', $slug)
            ->where('list_type', $listType->value)
            ->where('is_system', true)
            ->firstOrFail();

        $data = $request->validated();
        $data['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;
        $data['is_public'] = $request->has('is_public') ? (bool) $request->input('is_public') : false;

        // Handle slug changes
        if (isset($data['slug']) && $data['slug'] !== $list->slug) {
            $data['slug'] = Str::slug($data['slug']);
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $list->list_type->value, $list->id);
        }

        // Allow changing list_type for system lists
        if (isset($data['list_type']) && $data['list_type'] !== $list->list_type->value) {
            if (! in_array($data['list_type'], [
                ListTypeEnum::MONTHLY->value,
                ListTypeEnum::INDIE_GAMES->value,
                ListTypeEnum::SEASONED->value,
                ListTypeEnum::EVENTS->value,
                ListTypeEnum::HIGHLIGHTS->value,
            ])) {
                return redirect()->back()
                    ->withErrors(['list_type' => 'Invalid list type for system lists.'])
                    ->withInput();
            }
        }

        // Handle event_data for event-type lists
        if ($list->isEvents()) {
            $eventData = [
                'event_time' => $request->input('event_time'),
                'event_timezone' => $request->input('event_timezone'),
                'about' => $request->input('event_about'),
                'video_url' => $request->input('video_url'),
                'social_links' => array_filter([
                    'twitter' => $request->input('social_twitter'),
                    'youtube' => $request->input('social_youtube'),
                    'twitch' => $request->input('social_twitch'),
                    'discord' => $request->input('social_discord'),
                ]),
            ];
            $data['event_data'] = array_filter($eventData, fn ($value) => $value !== null && $value !== '');
        }

        $list->update($data);

        // Redirect to new type/slug if changed
        $newType = isset($data['list_type']) ? ListTypeEnum::from($data['list_type']) : $list->fresh()->list_type;
        $newSlug = $data['slug'] ?? $list->fresh()->slug;

        return redirect()->route('admin.system-lists.edit', [$newType->toSlug(), $newSlug])
            ->with('success', 'System list updated successfully.');
    }

    /**
     * Toggle active status of a system list.
     */
    public function toggleSystemListActive(string $type, string $slug): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $listType = ListTypeEnum::fromSlug($type);
        if ($listType === null) {
            abort(404, 'Invalid list type');
        }

        $list = GameList::where('slug', $slug)
            ->where('list_type', $listType->value)
            ->where('is_system', true)
            ->firstOrFail();

        $list->is_active = ! $list->is_active;
        $list->save();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $list->is_active,
            ]);
        }

        return redirect()->back()
            ->with('success', 'List status updated successfully.');
    }

    /**
     * Delete a system list (admin only).
     */
    public function destroySystemList(string $type, string $slug): RedirectResponse
    {
        $listType = ListTypeEnum::fromSlug($type);
        if ($listType === null) {
            abort(404, 'Invalid list type');
        }

        $list = GameList::where('slug', $slug)
            ->where('list_type', $listType->value)
            ->where('is_system', true)
            ->firstOrFail();

        $listName = $list->name;
        $list->delete();

        return redirect()->route('admin.system-lists')
            ->with('success', "System list '{$listName}' deleted successfully.");
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
            $slug = $originalSlug.'-'.$counter;
            $counter++;
            $query = GameList::where('slug', $slug)->where('list_type', $listType);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Add a game to a system list.
     */
    public function addGame(Request $request, string $type, string $slug): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        // Decode JSON strings from FormData
        $genreIds = $request->input('genre_ids');
        if (is_string($genreIds)) {
            $genreIds = json_decode($genreIds, true) ?? [];
            $request->merge(['genre_ids' => $genreIds]);
        }

        $request->validate([
            'game_id' => ['required'],
            'genre_ids' => ['nullable', 'array', 'max:3'],
            'genre_ids.*' => ['exists:genres,id'],
            'primary_genre_id' => ['nullable', 'exists:genres,id'],
            'is_tba' => ['nullable', 'boolean'],
        ]);

        $igdbId = $request->game_id;

        $game = Game::where('igdb_id', $igdbId)->first();

        if (! $game && is_numeric($igdbId)) {
            $game = Game::fetchFromIgdbIfMissing((int) $igdbId, app(IgdbService::class));
        }

        if (! $game) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Game not found.'], 404);
            }

            return redirect()->back()->with('error', 'Game not found.');
        }

        if ($list->games->contains($game->id)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['info' => 'Game is already in this list.']);
            }

            return redirect()->back()->with('info', 'Game is already in this list.');
        }

        $maxOrder = $list->games()->max('order') ?? 0;

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
                ->filter(fn ($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                ->map(fn ($p) => $p->igdb_id)
                ->values()
                ->toArray();
        }

        // Handle platform_group for highlights lists
        $platformGroup = $request->input('platform_group');
        if ($list->list_type === ListTypeEnum::HIGHLIGHTS && ! $platformGroup) {
            $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platformIds)->value;
        }

        // Handle genre data
        $genreIds = $request->input('genre_ids', []);
        if (is_string($genreIds)) {
            $genreIds = json_decode($genreIds, true) ?? [];
        }
        $primaryGenreId = $request->input('primary_genre_id');

        // Handle TBA flag
        $isTba = $request->boolean('is_tba', false);
        if ($isTba) {
            $releaseDate = null;
        }

        $list->games()->attach($game->id, [
            'order' => $maxOrder + 1,
            'release_date' => $releaseDate,
            'platforms' => json_encode($platformIds),
            'platform_group' => $platformGroup,
            'is_tba' => $isTba,
            'genre_ids' => json_encode(array_map('intval', $genreIds)),
            'primary_genre_id' => $primaryGenreId ? (int) $primaryGenreId : null,
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
     * Remove a game from a system list.
     */
    public function removeGame(string $type, string $slug, Game $game): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

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
     * Reorder games in a system list.
     */
    public function reorderGames(Request $request, string $type, string $slug): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

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
     * Update platform group for a game in a highlights list.
     */
    public function updateGamePlatformGroup(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if ($list->list_type !== ListTypeEnum::HIGHLIGHTS) {
            return response()->json(['error' => 'Platform group is only available for highlights lists.'], 400);
        }

        $request->validate([
            'platform_group' => ['required', 'string'],
        ]);

        $platformGroup = PlatformGroupEnum::tryFrom($request->input('platform_group'));
        if (! $platformGroup) {
            return response()->json(['error' => 'Invalid platform group.'], 400);
        }

        $list->games()->updateExistingPivot($game->id, [
            'platform_group' => $platformGroup->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Platform group updated.',
            'platform_group' => [
                'value' => $platformGroup->value,
                'label' => $platformGroup->label(),
                'color' => $platformGroup->colorClass(),
            ],
        ]);
    }

    /**
     * Toggle highlight status for a game in a monthly/indie list.
     * When toggled on, also adds the game to the yearly highlights list.
     * When toggled off, removes the game from the yearly highlights list.
     */
    public function toggleGameHighlight(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->canHaveHighlights()) {
            return response()->json(['error' => 'Highlight toggle is only available for monthly and indie lists.'], 400);
        }

        $pivotData = $list->games()->where('games.id', $game->id)->first()?->pivot;
        if (! $pivotData) {
            return response()->json(['error' => 'Game not found in this list.'], 404);
        }

        $newValue = ! (bool) $pivotData->is_highlight;

        if ($newValue) {
            $primaryGenreId = $request->input('primary_genre_id');
            if (! $primaryGenreId) {
                return response()->json(['error' => 'Genre is required when marking as highlight.'], 400);
            }

            $genreIds = $request->input('genre_ids', [$primaryGenreId]);
            if (is_string($genreIds)) {
                $genreIds = json_decode($genreIds, true) ?? [$primaryGenreId];
            }

            $isTba = (bool) $request->input('is_tba', false);
            $releaseDate = $isTba ? null : $request->input('release_date', $pivotData->release_date);

            $platforms = $request->input('platforms', $pivotData->platforms);
            if (is_array($platforms)) {
                $platforms = json_encode(array_map('intval', $platforms));
            }

            $list->games()->updateExistingPivot($game->id, [
                'is_highlight' => true,
                'genre_ids' => json_encode(array_map('intval', $genreIds)),
                'primary_genre_id' => (int) $primaryGenreId,
                'release_date' => $releaseDate,
                'is_tba' => $isTba,
                'platforms' => $platforms,
            ]);

            $this->syncGameToYearlyHighlights($list, $game, $pivotData, true, $genreIds, $primaryGenreId, $releaseDate, $isTba, $platforms);
        } else {
            $list->games()->updateExistingPivot($game->id, [
                'is_highlight' => false,
            ]);

            $this->syncGameToYearlyHighlights($list, $game, $pivotData, false);
        }

        return response()->json([
            'success' => true,
            'message' => $newValue ? 'Game marked as highlight.' : 'Highlight removed from game.',
            'is_highlight' => $newValue,
        ]);
    }

    /**
     * Toggle indie status for a game in a monthly/seasoned list.
     * When toggled on, also adds the game to the yearly indie list.
     */
    public function toggleGameIndie(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->canMarkAsIndie()) {
            return response()->json(['error' => 'Indie toggle is only available for monthly and seasoned lists.'], 400);
        }

        $pivotData = $list->games()->where('games.id', $game->id)->first()?->pivot;
        if (! $pivotData) {
            return response()->json(['error' => 'Game not found in this list.'], 404);
        }

        $currentValue = (bool) $pivotData->is_indie;
        $newValue = ! $currentValue;

        if ($newValue) {
            $primaryGenreId = $request->input('primary_genre_id');
            if (! $primaryGenreId) {
                return response()->json(['error' => 'Genre is required when marking as indie.'], 400);
            }

            $genreIds = $request->input('genre_ids', [$primaryGenreId]);
            if (is_string($genreIds)) {
                $genreIds = json_decode($genreIds, true) ?? [$primaryGenreId];
            }

            $isTba = (bool) $request->input('is_tba', false);
            $releaseDate = $isTba ? null : $request->input('release_date', $pivotData->release_date);

            $platforms = $request->input('platforms', $pivotData->platforms);
            if (is_array($platforms)) {
                $platforms = json_encode(array_map('intval', $platforms));
            }

            $list->games()->updateExistingPivot($game->id, [
                'is_indie' => true,
                'genre_ids' => json_encode(array_map('intval', $genreIds)),
                'primary_genre_id' => (int) $primaryGenreId,
                'release_date' => $releaseDate,
                'is_tba' => $isTba,
                'platforms' => $platforms,
            ]);

            $this->syncGameToYearlyIndies($list, $game, $pivotData, true, $genreIds, $primaryGenreId, $releaseDate, $isTba, $platforms);
        } else {
            $list->games()->updateExistingPivot($game->id, [
                'is_indie' => false,
            ]);

            $this->syncGameToYearlyIndies($list, $game, $pivotData, false);
        }

        return response()->json([
            'success' => true,
            'message' => $newValue ? 'Game marked as indie.' : 'Indie status removed from game.',
            'is_indie' => $newValue,
        ]);
    }

    /**
     * Sync a game to/from the yearly indie list based on indie status.
     */
    protected function syncGameToYearlyIndies(GameList $sourceList, Game $game, $pivotData, bool $isIndie, ?array $genreIds = null, ?int $primaryGenreId = null, mixed $releaseDate = null, bool $isTba = false, mixed $platforms = null): void
    {
        $year = $sourceList->start_at?->year ?? now()->year;
        $startOfYear = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = \Carbon\Carbon::create($year, 12, 31)->endOfDay();

        $indieList = GameList::where('list_type', ListTypeEnum::INDIE_GAMES->value)
            ->where('is_system', true)
            ->whereBetween('start_at', [$startOfYear, $endOfYear])
            ->first();

        if (! $indieList) {
            return;
        }

        if ($isIndie) {
            if (! $indieList->games()->where('games.id', $game->id)->exists()) {
                // Use passed platforms or fallback to pivot data
                if ($platforms === null) {
                    $platforms = $pivotData->platforms;
                }
                if (is_string($platforms)) {
                    $platforms = json_decode($platforms, true) ?? [];
                }
                if (! is_array($platforms) || empty($platforms)) {
                    $game->load('platforms');
                    $platforms = $game->platforms
                        ->filter(fn ($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                        ->map(fn ($p) => $p->igdb_id)
                        ->values()
                        ->toArray();
                }

                $maxOrder = $indieList->games()->max('order') ?? 0;

                $indieList->games()->attach($game->id, [
                    'order' => $maxOrder + 1,
                    'release_date' => $releaseDate ?? $pivotData->release_date,
                    'platforms' => json_encode($platforms),
                    'is_tba' => $isTba,
                    'genre_ids' => json_encode($genreIds ?? []),
                    'primary_genre_id' => $primaryGenreId,
                ]);
            }
        } else {
            // Remove from indie list
            $indieList->games()->detach($game->id);
        }
    }

    /**
     * Get game genres for the genre selection modal.
     */
    public function getGameGenres(string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->games()->where('games.id', $game->id)->exists()) {
            return response()->json(['error' => 'Game not found in this list.'], 404);
        }

        $game->load(['genres', 'platforms']);
        $igdbGenres = $game->genres->map(fn ($genre) => [
            'id' => $genre->id,
            'name' => $genre->name,
            'slug' => $genre->slug ?? str()->slug($genre->name),
        ])->toArray();

        $pivotData = $list->games()->where('games.id', $game->id)->first()?->pivot;

        $releaseDate = $pivotData->release_date ?? $game->first_release_date;
        if ($releaseDate instanceof \Carbon\Carbon) {
            $releaseDate = $releaseDate->format('Y-m-d');
        } elseif ($releaseDate && is_string($releaseDate)) {
            $releaseDate = \Carbon\Carbon::parse($releaseDate)->format('Y-m-d');
        }

        $genreIds = $pivotData->genre_ids ?? null;
        if (is_string($genreIds)) {
            $genreIds = json_decode($genreIds, true) ?? [];
        }

        // Get game's own platforms from IGDB (filtered to active platforms)
        $gamePlatforms = $game->platforms
            ->filter(fn ($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
            ->map(fn ($p) => $p->igdb_id)
            ->values()
            ->toArray();

        return response()->json([
            'igdb_genres' => $igdbGenres,
            'genre_ids' => $genreIds ?? [],
            'primary_genre_id' => $pivotData->primary_genre_id ?? null,
            'is_indie' => (bool) ($pivotData->is_indie ?? false),
            'release_date' => $releaseDate,
            'is_tba' => (bool) ($pivotData->is_tba ?? false),
            'platforms' => $gamePlatforms,
            'game_name' => $game->name,
            'cover_url' => $game->getCoverUrl('cover_big'),
        ]);
    }

    /**
     * Update pivot data for a game in a system list (without toggling highlight/indie).
     */
    public function updateGamePivotData(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->games()->where('games.id', $game->id)->exists()) {
            return response()->json(['error' => 'Game not found in this list.'], 404);
        }

        $genreIds = $request->input('genre_ids');
        if (is_string($genreIds)) {
            $request->merge(['genre_ids' => json_decode($genreIds, true) ?? []]);
        }

        $request->validate([
            'release_date' => ['nullable', 'date'],
            'platforms' => ['nullable', 'array'],
            'is_tba' => ['nullable', 'boolean'],
            'genre_ids' => ['nullable', 'array', 'max:3'],
            'genre_ids.*' => ['exists:genres,id'],
            'primary_genre_id' => ['nullable', 'exists:genres,id'],
        ]);

        $isTba = $request->boolean('is_tba', false);
        $releaseDate = $isTba ? null : $request->input('release_date');

        $pivotUpdate = [
            'release_date' => $releaseDate,
            'is_tba' => $isTba,
        ];

        $platforms = $request->input('platforms');
        if (is_array($platforms)) {
            $pivotUpdate['platforms'] = json_encode(array_map('intval', $platforms));
        }

        $genreIds = $request->input('genre_ids');
        if (is_array($genreIds)) {
            $pivotUpdate['genre_ids'] = json_encode(array_map('intval', $genreIds));
        }

        if ($request->has('primary_genre_id')) {
            $pivotUpdate['primary_genre_id'] = $request->input('primary_genre_id') ? (int) $request->input('primary_genre_id') : null;
        }

        $list->games()->updateExistingPivot($game->id, $pivotUpdate);

        return response()->json([
            'success' => true,
            'message' => 'Game data updated successfully.',
        ]);
    }

    /**
     * Update game genres in a system list.
     */
    public function updateGameGenres(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->games()->where('games.id', $game->id)->exists()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Game not found in this list.'], 404);
            }

            return redirect()->back()->with('error', 'Game not found in this list.');
        }

        $validated = $request->validate([
            'genre_ids' => ['nullable', 'array', 'max:3'],
            'genre_ids.*' => ['exists:genres,id'],
            'primary_genre_id' => ['nullable', 'exists:genres,id'],
        ]);

        $genreIds = $validated['genre_ids'] ?? [];
        $primaryGenreId = $validated['primary_genre_id'] ?? null;

        $list->games()->updateExistingPivot($game->id, [
            'genre_ids' => json_encode(array_map('intval', $genreIds)),
            'primary_genre_id' => $primaryGenreId ? (int) $primaryGenreId : null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Game genres updated successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Game genres updated.');
    }

    /**
     * Sync a game to/from the yearly highlights list based on highlight status.
     */
    protected function syncGameToYearlyHighlights(GameList $sourceList, Game $game, $pivotData, bool $isHighlight, ?array $genreIds = null, ?int $primaryGenreId = null, mixed $releaseDate = null, bool $isTba = false, mixed $platforms = null): void
    {
        $year = $sourceList->start_at?->year ?? now()->year;
        $startOfYear = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = \Carbon\Carbon::create($year, 12, 31)->endOfDay();

        $highlightsList = GameList::where('list_type', ListTypeEnum::HIGHLIGHTS->value)
            ->where('is_system', true)
            ->whereBetween('start_at', [$startOfYear, $endOfYear])
            ->first();

        if (! $highlightsList) {
            return;
        }

        if ($isHighlight) {
            // Add to highlights list if not already there
            if (! $highlightsList->games()->where('games.id', $game->id)->exists()) {
                // Use passed platforms or fallback to pivot data
                if ($platforms === null) {
                    $platforms = $pivotData->platforms;
                }
                if (is_string($platforms)) {
                    $platforms = json_decode($platforms, true) ?? [];
                }
                if (! is_array($platforms) || empty($platforms)) {
                    // Fallback to game's own platforms
                    $game->load('platforms');
                    $platforms = $game->platforms
                        ->filter(fn ($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                        ->map(fn ($p) => $p->igdb_id)
                        ->values()
                        ->toArray();
                }

                $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platforms);
                $maxOrder = $highlightsList->games()->max('order') ?? 0;

                $highlightsList->games()->attach($game->id, [
                    'order' => $maxOrder + 1,
                    'release_date' => $releaseDate ?? $pivotData->release_date,
                    'platforms' => json_encode($platforms),
                    'platform_group' => $platformGroup->value,
                    'is_highlight' => false,
                    'genre_ids' => $genreIds ? json_encode(array_map('intval', $genreIds)) : null,
                    'primary_genre_id' => $primaryGenreId,
                    'is_tba' => $isTba,
                ]);
            }
        } else {
            // Remove from highlights list
            $highlightsList->games()->detach($game->id);
        }
    }

    /**
     * Helper: Get system list by type and slug.
     */
    protected function getSystemListByTypeAndSlug(string $type, string $slug): GameList
    {
        $listType = ListTypeEnum::fromSlug($type);
        if ($listType === null) {
            abort(404, 'Invalid list type');
        }

        return GameList::where('slug', $slug)
            ->where('list_type', $listType->value)
            ->where('is_system', true)
            ->with(['games' => function ($query) {
                $query->orderByPivot('order');
            }])
            ->firstOrFail();
    }
}
