<?php

namespace App\Http\Controllers;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Enums\PlatformGroupEnum;
use App\Http\Requests\StoreGameListRequest;
use App\Http\Requests\UpdateGameListRequest;
use App\Models\Game;
use App\Models\GameList;
use App\Services\IgdbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminListController extends Controller
{
    public function myLists(): RedirectResponse
    {
        return redirect()->route('user.lists.lists', ['user' => auth()->user()->username], 301);
    }

    public function systemLists(Request $request): View
    {
        // Get yearly lists ordered by start_at desc
        $yearlyLists = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::YEARLY)
            ->withCount('games')
            ->orderByDesc('start_at')
            ->get()
            ->map(function ($list) {
                $list->highlights_count = $list->games()
                    ->wherePivot('is_highlight', true)
                    ->count();
                $list->indie_count = $list->games()
                    ->wherePivot('is_indie', true)
                    ->count();

                return $list;
            });

        // Get only active seasoned lists
        $seasonedLists = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::SEASONED)
            ->where('is_active', true)
            ->withCount('games')
            ->orderBy('name')
            ->get();

        // Get all events lists
        $eventsLists = GameList::where('is_system', true)
            ->where('list_type', ListTypeEnum::EVENTS)
            ->withCount('games')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.system-lists.index', compact(
            'yearlyLists',
            'seasonedLists',
            'eventsLists'
        ));
    }

    public function userLists(Request $request): View
    {
        $query = GameList::with(['user', 'games'])
            ->whereNotNull('user_id')
            ->where('is_system', false);

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

        $lists = $query->get()
            ->sortBy(function ($list) {
                return $list->user->username ?? '';
            })
            ->groupBy(function ($list) {
                return $list->user_id;
            });

        return view('admin.user-lists', compact('lists'));
    }

    public function createSystemList(): View
    {
        return view('admin.system-lists.create');
    }

    public function storeSystemList(StoreGameListRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['is_system'] = true;
        $data['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;
        $data['is_public'] = $request->has('is_public') ? (bool) $request->input('is_public') : false;

        if (! in_array($data['list_type'], [
            ListTypeEnum::YEARLY->value,
            ListTypeEnum::SEASONED->value,
            ListTypeEnum::EVENTS->value,
        ])) {
            return redirect()->back()
                ->withErrors(['list_type' => 'Invalid list type for system lists.'])
                ->withInput();
        }

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

        if (isset($data['slug']) && $data['slug'] !== $list->slug) {
            $data['slug'] = Str::slug($data['slug']);
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $list->list_type->value, $list->id);
        }

        if (isset($data['list_type']) && $data['list_type'] !== $list->list_type->value) {
            if (! in_array($data['list_type'], [
                ListTypeEnum::YEARLY->value,
                ListTypeEnum::SEASONED->value,
                ListTypeEnum::EVENTS->value,
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

        $newType = isset($data['list_type']) ? ListTypeEnum::from($data['list_type']) : $list->fresh()->list_type;
        $newSlug = $data['slug'] ?? $list->fresh()->slug;

        return redirect()->route('admin.system-lists.edit', [$newType->toSlug(), $newSlug])
            ->with('success', 'System list updated successfully.');
    }

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

    protected function generateUniqueSlug(string $name, string $listType, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);

        return $this->ensureUniqueSlug($slug, $listType, $excludeId);
    }

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

        // Auto-set platform_group for yearly lists
        $platformGroup = $request->input('platform_group');
        if ($list->isYearly() && ! $platformGroup) {
            $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platformIds)->value;
        }

        $genreIds = $request->input('genre_ids', []);
        if (is_string($genreIds)) {
            $genreIds = json_decode($genreIds, true) ?? [];
        }
        $primaryGenreId = $request->input('primary_genre_id');

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

    public function updateGamePlatformGroup(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->isYearly()) {
            return response()->json(['error' => 'Platform group is only available for yearly lists.'], 400);
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

    public function toggleGameHighlight(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->canHaveHighlights()) {
            return response()->json(['error' => 'Highlight toggle is only available for yearly lists.'], 400);
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
        } else {
            $list->games()->updateExistingPivot($game->id, [
                'is_highlight' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $newValue ? 'Game marked as highlight.' : 'Highlight removed from game.',
            'is_highlight' => $newValue,
        ]);
    }

    public function toggleGameIndie(Request $request, string $type, string $slug, Game $game): \Illuminate\Http\JsonResponse
    {
        $list = $this->getSystemListByTypeAndSlug($type, $slug);

        if (! $list->canMarkAsIndie()) {
            return response()->json(['error' => 'Indie toggle is only available for yearly and seasoned lists.'], 400);
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

            // If toggling on a seasoned list, sync to the yearly list for that year
            if ($list->isSeasoned()) {
                $this->syncIndieToYearlyList($list, $game, $pivotData, true, $genreIds, $primaryGenreId, $releaseDate, $isTba, $platforms);
            }
        } else {
            $list->games()->updateExistingPivot($game->id, [
                'is_indie' => false,
            ]);

            if ($list->isSeasoned()) {
                $this->syncIndieToYearlyList($list, $game, $pivotData, false);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $newValue ? 'Game marked as indie.' : 'Indie status removed from game.',
            'is_indie' => $newValue,
        ]);
    }

    /**
     * Sync indie status from a seasoned list to the matching yearly list.
     */
    protected function syncIndieToYearlyList(GameList $sourceList, Game $game, $pivotData, bool $isIndie, ?array $genreIds = null, ?int $primaryGenreId = null, mixed $releaseDate = null, bool $isTba = false, mixed $platforms = null): void
    {
        $year = $sourceList->start_at?->year ?? now()->year;

        $yearlyList = GameList::yearly()
            ->where('is_system', true)
            ->whereYear('start_at', $year)
            ->first();

        if (! $yearlyList) {
            return;
        }

        if ($isIndie) {
            if ($yearlyList->games()->where('games.id', $game->id)->exists()) {
                $yearlyList->games()->updateExistingPivot($game->id, ['is_indie' => true]);
            } else {
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

                $platformGroup = PlatformGroupEnum::suggestFromPlatforms(is_array($platforms) ? $platforms : [])->value;
                $maxOrder = $yearlyList->games()->max('order') ?? 0;

                $yearlyList->games()->attach($game->id, [
                    'order' => $maxOrder + 1,
                    'release_date' => $releaseDate ?? $pivotData->release_date,
                    'platforms' => is_string($platforms) ? $platforms : json_encode($platforms),
                    'platform_group' => $platformGroup,
                    'is_tba' => $isTba,
                    'is_indie' => true,
                    'genre_ids' => json_encode($genreIds ?? []),
                    'primary_genre_id' => $primaryGenreId,
                ]);
            }
        } else {
            if ($yearlyList->games()->where('games.id', $game->id)->exists()) {
                $yearlyList->games()->updateExistingPivot($game->id, ['is_indie' => false]);
            }
        }
    }

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
