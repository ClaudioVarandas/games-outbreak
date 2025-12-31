<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\Platform;
use App\Services\IgdbService;
use Carbon\Carbon;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class GamesController extends Controller
{
    public function upcoming(Request $request): View
    {
        $today = Carbon::today();
        $maxDate = $today->copy()->addDays(90);

        // Get filter parameters from request
        $startDate = $request->query('start_date')
            ? Carbon::createFromFormat('Y-m-d', $request->query('start_date'))->startOfDay()
            : $today;

        $endDate = $request->query('end_date')
            ? Carbon::createFromFormat('Y-m-d', $request->query('end_date'))->endOfDay()
            : $maxDate;

        // Validate date range
        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        // Ensure max 90 days range
        if ($endDate->diffInDays($startDate) > 90) {
            $endDate = $startDate->copy()->addDays(90)->endOfDay();
        }

        // Ensure dates don't exceed max
        if ($startDate->gt($maxDate)) {
            $startDate = $today;
        }
        if ($endDate->gt($maxDate)) {
            $endDate = $maxDate;
        }

        // Build query
        $query = Game::with(['platforms', 'genres', 'gameModes'])
            ->whereNotNull('first_release_date')
            ->whereBetween('first_release_date', [$startDate, $endDate]);

        // Apply genre filter
        $genreParams = $request->query('genres', []);
        if (!is_array($genreParams)) {
            $genreParams = [$genreParams];
        }
        $genreIds = array_filter(array_map('intval', $genreParams));
        if (!empty($genreIds)) {
            $query->whereHas('genres', function ($q) use ($genreIds) {
                $q->whereIn('genres.id', $genreIds);
            });
        }

        // Apply platform filter
        $platformParams = $request->query('platforms', []);
        if (!is_array($platformParams)) {
            $platformParams = [$platformParams];
        }
        $platformIds = array_filter(array_map('intval', $platformParams));
        if (!empty($platformIds)) {
            $query->whereHas('platforms', function ($q) use ($platformIds) {
                $q->whereIn('igdb_id', $platformIds);
            });
        }

        // Apply game mode filter
        $modeParams = $request->query('game_modes', []);
        if (!is_array($modeParams)) {
            $modeParams = [$modeParams];
        }
        $modeIds = array_filter(array_map('intval', $modeParams));
        if (!empty($modeIds)) {
            $query->whereHas('gameModes', function ($q) use ($modeIds) {
                $q->whereIn('game_modes.id', $modeIds);
            });
        }

        // Apply game type filter
        $gameTypeParams = $request->query('game_types', []);
        if (!is_array($gameTypeParams)) {
            $gameTypeParams = [$gameTypeParams];
        }
        $gameTypeIds = array_filter(array_map('intval', $gameTypeParams));
        if (!empty($gameTypeIds)) {
            $query->whereIn('game_type', $gameTypeIds);
        }

        // Order and paginate
        $games = $query->orderBy('first_release_date')
            ->paginate(24)
            ->appends($request->query());

        // Get filter options for the UI
        $platformEnums = PlatformEnum::getActivePlatforms();
        $allGenres = Genre::orderBy('name')->get();
        $allGameModes = GameMode::orderBy('name')->get();
        $allGameTypes = \App\Enums\GameTypeEnum::cases();

        // Get active filter values (ensure arrays)
        $activeFilters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'genres' => is_array($request->query('genres', [])) ? $request->query('genres', []) : (($request->query('genres')) ? [$request->query('genres')] : []),
            'platforms' => is_array($request->query('platforms', [])) ? $request->query('platforms', []) : (($request->query('platforms')) ? [$request->query('platforms')] : []),
            'game_modes' => is_array($request->query('game_modes', [])) ? $request->query('game_modes', []) : (($request->query('game_modes')) ? [$request->query('game_modes')] : []),
            'game_types' => is_array($request->query('game_types', [])) ? $request->query('game_types', []) : (($request->query('game_types')) ? [$request->query('game_types')] : []),
        ];

        return view('upcoming.index', compact(
            'games',
            'platformEnums',
            'allGenres',
            'allGameModes',
            'allGameTypes',
            'activeFilters',
            'startDate',
            'endDate',
            'today',
            'maxDate'
        ));
    }

    public function show($igdbId, IgdbService $igdb): View
    {
        // Try to find existing game
        $game = Game::with(['platforms', 'genres', 'gameModes', 'companies', 'gameEngines', 'playerPerspectives', 'releaseDates.platform', 'releaseDates.status'])
            ->where('igdb_id', $igdbId)
            ->first();

        // If exists → show it
        if ($game) {
            // Track view and update priority
            $game->markAsViewed();

            // Check if game is missing critical relationship data (created from search with minimal data)
            $isMissingRelationships = $game->companies->isEmpty()
                || $game->genres->isEmpty()
                || $game->gameModes->isEmpty();

            // Trigger background update if:
            // 1. Data is stale (30+ days), OR
            // 2. Missing critical relationships (likely created from search results)
            if ($game->shouldUpdate(30) || $isMissingRelationships) {
                \App\Jobs\RefreshGameData::dispatch($game->id, false);
            }

            $platformEnums = PlatformEnum::getActivePlatforms();
            return view('games.show', compact('game', 'platformEnums'));
        }

        // Not in DB → fetch on-demand
        try {
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

            $response = Http::igdb()
                ->withBody($query, 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            if ($response->failed() || empty($response->json())) {
                abort(404, 'Game not found');
            }

            $igdbGame = $response->json()[0];

            // Enrich with Steam
            $igdbGame = $igdb->enrichWithSteamData([$igdbGame])[0] ?? $igdbGame;

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

            // Save to DB
            $game = Game::create([
                'igdb_id' => $igdbGame['id'],
                'name' => $gameName,
                'summary' => $igdbGame['summary'] ?? null,
                'first_release_date' => isset($igdbGame['first_release_date'])
                    ? Carbon::createFromTimestamp($igdbGame['first_release_date'])
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

            // Sync relations and release dates
            $this->syncRelations($game, $igdbGame);
            Game::syncReleaseDates($game, $igdbGame['release_dates'] ?? null);

            // Dispatch job to fetch missing images asynchronously
            if (!empty($imagesToFetch)) {
                \App\Jobs\FetchGameImages::dispatch(
                    $game->id,
                    $gameName,
                    $steamAppId,
                    $igdbGameId,
                    $imagesToFetch
                );
            }

            $game->load(['platforms', 'genres', 'gameModes']);

            // Track initial view for newly created game
            $game->markAsViewed();

            $platformEnums = PlatformEnum::getActivePlatforms();
            return view('games.show', compact('game', 'platformEnums'));

        } catch (\Exception $e) {
            \Log::error("On-demand fetch failed for IGDB ID {$igdbId}", ['error' => $e->getMessage()]);
            abort(404, 'Game temporarily unavailable');
        }
    }

    /**
     * Get similar games HTML (for AJAX loading)
     */
    public function similarGamesHtml(Game $game): View
    {
        $platformEnums = PlatformEnum::getActivePlatforms();
        return view('games.partials.similar-games', compact('game', 'platformEnums'));
    }

    /**
     * Get similar games JSON (for AJAX loading)
     */
    public function similarGames(Game $game): JsonResponse
    {
        if (!$game->similar_games || empty($game->similar_games)) {
            return response()->json(['games' => []]);
        }

        $igdbService = app(IgdbService::class);
        $platformEnums = PlatformEnum::getActivePlatforms();

        $similarGames = collect($game->similar_games)
            ->take(12)
            ->map(function ($similar) use ($igdbService, $platformEnums) {
                $igdbId = $similar['id'] ?? null;
                if (!$igdbId) {
                    return null;
                }

                // Try to find the game in the database, or fetch from IGDB if missing
                $similarGame = Game::fetchFromIgdbIfMissing($igdbId, $igdbService);
                if (!$similarGame) {
                    return null;
                }

                $similarGame->load('platforms');

                // Format for JSON response
                return [
                    'igdb_id' => $similarGame->igdb_id,
                    'name' => $similarGame->name,
                    'cover_url' => $similarGame->cover_image_id
                        ? $similarGame->getCoverUrl('cover_big')
                        : null,
                    'platforms' => $similarGame->platforms
                        ->filter(fn($p) => $platformEnums->has($p->igdb_id))
                        ->sortBy(fn($p) => PlatformEnum::getPriority($p->igdb_id))
                        ->map(fn($p) => PlatformEnum::fromIgdbId($p->igdb_id)?->label() ?? $p->name)
                        ->values()
                        ->toArray(),
                    'release_date' => $similarGame->first_release_date?->format('d/m/Y'),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['games' => $similarGames]);
    }

    public function mostWanted(): View
    {
        $today = Carbon::today();
        $futureLimit = $today->copy()->addMonths(6); // Look ahead 6 months for "most wanted"

        $games = Game::with(['platforms', 'genres', 'gameModes'])
            ->whereNotNull('first_release_date')
            ->whereBetween('first_release_date', [$today, $futureLimit])
            ->get()
            ->map(function ($game) use ($today) {
                // Calculate Wanted Score (0–100)
                $score = 0;

                // 1. Steam wishlist proxy via recommendations (if available)
                $recommendations = $game->steam_data['recommendations'] ?? 0;
                $recScore = min($recommendations / 10000, 1) * 40; // Cap at ~10k recs = 40 points

                // 2. Genre popularity boost (example weights)
                $genreBoost = 0;
                foreach ($game->genres as $genre) {
                    $popularGenres = ['Role-playing (RPG)', 'Shooter', 'Adventure', 'Indie', 'Strategy'];
                    if (in_array($genre->name, $popularGenres)) {
                        $genreBoost += 8;
                    }
                }
                $genreBoost = min($genreBoost, 30); // Max 30 points

                // 3. Multiplayer/Co-op hype
                $modeBoost = 0;
                foreach ($game->gameModes as $mode) {
                    if (in_array($mode->name, ['Multiplayer', 'Co-operative', 'Massively Multiplayer Online (MMO)'])) {
                        $modeBoost += 15;
                    }
                }
                $modeBoost = min($modeBoost, 20);

                // 4. Recent release proximity (closer = more hype)
                $daysUntil = $game->first_release_date->diffInDays($today, false);
                $proximityBonus = $daysUntil > 0 ? min(100 / max($daysUntil, 1), 10) : 0;

                $score = $recScore + $genreBoost + $modeBoost + $proximityBonus;

                $wishlistCount = $game->steam_wishlist_count ?? 0;
                $wishlistScore = $wishlistCount > 0 ? min(log($wishlistCount, 10) * 20, 60) : 0; // Log scale: 1M → ~60 points
                $score += $wishlistScore;

                $game->wanted_score = round($score, 1);

                return $game;
            })
            ->sortByDesc('wanted_score')
            ->take(12); // Top 50 most wanted

        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('most-wanted.index', compact('games', 'platformEnums'));
    }


    /**
     * category is deprecated
     * Future-Proof with game_type: "where name ~ *\"%s\"* & game_type = 0;"
     *
     * @param Request $request
     * @return JsonResponse|null
     */

    public function search(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $query = trim($request->query('q', ''));
        $platforms = $request->query('platforms');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Check if query is an IGDB ID (numeric)
        if (is_numeric($query) && ctype_digit($query)) {
            return $this->searchByIgdbId((int)$query);
        }

        try {
            // Determine which platforms to use
            if ($platforms) {
                $platformIds = is_array($platforms)
                    ? $platforms
                    : explode(',', $platforms);

                $validPlatformIds = collect($platformIds)
                    ->map(fn($id) => (int)trim($id))
                    ->filter(fn($id) => PlatformEnum::tryFrom($id) !== null)
                    ->values()
                    ->toArray();
            } else {
                $validPlatformIds = PlatformEnum::getActivePlatforms()
                    ->keys()
                    ->toArray();
            }

            // Normalize query: remove common punctuation and split into words
            // This allows "Dynasty Warriors Origins" to match "Dynasty Warriors: Origins"
            $normalizedQuery = preg_replace('/[:;,\-\.]/', ' ', $query);
            $normalizedQuery = preg_replace('/\s+/', ' ', $normalizedQuery);
            $normalizedQuery = trim($normalizedQuery);

            // Split into words and build IGDB query that matches all words
            // This works around IGDB's issue where removing punctuation breaks exact phrase matching
            $words = array_filter(explode(' ', $normalizedQuery), fn($w) => strlen($w) > 0);
            $platformsList = implode(',', $validPlatformIds);

            // Build IGDB query: search for each word individually (AND logic)
            // This allows "Dynasty Warriors Origins" to match "Dynasty Warriors: Origins"
            $nameConditions = [];
            foreach ($words as $word) {
                $escapedWord = str_replace('"', "'", $word);
                $nameConditions[] = 'name ~ *"' . $escapedWord . '"*';
            }
            $nameWhereClause = implode(' & ', $nameConditions);

            // Use 'game_type' instead of deprecated 'category'
            // Included game_type values: 0 = main game, 1 = DLC/Add-on, 2 = expansion, 3 = port, 4 = standalone expansion, 5 = bundle, 8 = remake, 9 = remaster, 10 = expanded game
            // First query: games matching name AND platform filter
            $igdbQuery = 'fields name, first_release_date, cover.image_id, platforms.id, platforms.name, game_type, category, collection; ' .
                'where ' . $nameWhereClause . ' ' .
                '& platforms = (' . $platformsList . ') ' .
                '& game_type = (0, 1, 2, 3, 4, 5, 8, 9, 10); ' .
                'sort first_release_date desc; ' .
                'limit 8;';

            // Second query: bundles/ports with "Bundle" in name (without platform filter, like IGDB website does)
            $bundleQuery = 'fields name, first_release_date, cover.image_id, platforms.id, platforms.name, game_type, category, collection; ' .
                'where ' . $nameWhereClause . ' ' .
                '& (name ~ *"Bundle"* | name ~ *"Collection"*) ' .
                '& game_type = (3, 5); ' .
                'sort first_release_date desc; ' .
                'limit 3;';


            $response = Http::igdb()
                ->withBody($igdbQuery, 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            $igdbResponseData = $response->json() ?? [];

            // Fetch bundles separately (without platform filter, like IGDB website)
            $bundleResponse = Http::igdb()
                ->withBody($bundleQuery, 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            $bundleData = $bundleResponse->json() ?? [];

            // Merge results: add bundles at the beginning if they're not already in results
            $existingIds = collect($igdbResponseData)->pluck('id')->toArray();
            $newBundles = collect($bundleData)->filter(fn($bundle) => !in_array($bundle['id'] ?? null, $existingIds))->toArray();

            // Prepend bundles to results (like IGDB website does)
            $igdbResponseData = array_merge($newBundles, $igdbResponseData);

            // Limit to 8 total results
            $igdbResponseData = array_slice($igdbResponseData, 0, 8);



            if ($response->failed() || empty($igdbResponseData)) {
                return response()->json([]);
            }

            $igdbResults = collect($igdbResponseData)->map(function ($game) {
                $gameType = isset($game['game_type']) ? (int)$game['game_type'] : 0;
                $gameName = $game['name'] ?? 'Unknown Game';

                // Detect bundles by name (IGDB sometimes classifies bundles as PORT/3 instead of BUNDLE/5)
                $isBundle = stripos($gameName, 'Bundle') !== false || stripos($gameName, 'Collection') !== false;

                // If it's a bundle by name, treat it as bundle regardless of game_type
                if ($isBundle && $gameType !== 5) {
                    $gameType = 5; // Force to BUNDLE
                }

                $gameTypeEnum = \App\Enums\GameTypeEnum::fromValue($gameType) ?? \App\Enums\GameTypeEnum::MAIN;
                $gameTypeLabel = $gameTypeEnum->label();

                $platformIds = collect($game['platforms'] ?? [])
                    ->map(fn($p) => $p['id'] ?? null)
                    ->filter()
                    ->values()
                    ->toArray();

                $platformLabels = collect($game['platforms'] ?? [])
                    ->map(function ($platform) {
                        $platformEnum = PlatformEnum::fromIgdbId($platform['id'] ?? 0);
                        return $platformEnum?->label() ?? $platform['name'] ?? 'Unknown';
                    })
                    ->implode(', ');

                $releaseDate = isset($game['first_release_date'])
                    ? \Carbon\Carbon::createFromTimestamp($game['first_release_date'])
                    : null;

                return [
                    'igdb_id' => $game['id'],
                    'name' => $gameName,
                    'release' => $releaseDate ? $releaseDate->format('d/m/Y') : 'TBA',
                    'release_date' => $releaseDate ? $releaseDate->format('Y-m-d') : null,
                    'cover_url' => isset($game['cover']['image_id'])
                        ? "https://images.igdb.com/igdb/image/upload/t_cover_small/{$game['cover']['image_id']}.jpg"
                        : 'https://via.placeholder.com/90x120/1f2937/6b7280?text=No+Cover',
                    'platforms' => $platformLabels,
                    'platform_ids' => $platformIds,
                    'game_type' => $gameType,
                    'game_type_label' => $gameTypeLabel,
                ];
            });

            return response()->json($igdbResults);

        } catch (\Exception $e) {
            \Log::error('IGDB search exception', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return response()->json([]);
        }
    }

    private function searchByIgdbId(int $igdbId): JsonResponse
    {
        try {
            $igdbService = app(IgdbService::class);
            $game = Game::fetchFromIgdbIfMissing($igdbId, $igdbService);

            if (!$game) {
                return response()->json([]);
            }

            // Format to match existing API response structure
            $gameTypeEnum = $game->getGameTypeEnum();

            $platformIds = $game->platforms
                ->filter(fn($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                ->map(fn($p) => $p->igdb_id)
                ->values()
                ->toArray();

            $platformLabels = $game->platforms
                ->filter(fn($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                ->sortBy(fn($p) => PlatformEnum::getPriority($p->igdb_id))
                ->map(fn($p) => PlatformEnum::fromIgdbId($p->igdb_id)?->label() ?? $p->name)
                ->implode(', ');

            return response()->json([[
                'igdb_id' => $game->igdb_id,
                'name' => $game->name,
                'release' => $game->first_release_date
                    ? $game->first_release_date->format('d/m/Y')
                    : 'TBA',
                'release_date' => $game->first_release_date
                    ? $game->first_release_date->format('Y-m-d')
                    : null,
                'cover_url' => $game->cover_image_id
                    ? $game->getCoverUrl('cover_small')
                    : 'https://via.placeholder.com/90x120/1f2937/6b7280?text=No+Cover',
                'platforms' => $platformLabels,
                'platform_ids' => $platformIds,
                'game_type' => $game->game_type ?? 0,
                'game_type_label' => $gameTypeEnum->label(),
            ]]);
        } catch (\Exception $e) {
            \Log::error('IGDB ID search exception', [
                'igdb_id' => $igdbId,
                'error' => $e->getMessage()
            ]);
            return response()->json([]);
        }
    }

    private function searchResultsByIgdbId(int $igdbId, string $query, string $viewMode): View
    {
        try {
            $igdbService = app(IgdbService::class);
            $game = Game::fetchFromIgdbIfMissing($igdbId, $igdbService);

            if (!$game) {
                return view('search.results', [
                    'games' => collect(),
                    'query' => $query,
                    'viewMode' => $viewMode,
                    'totalResults' => 0,
                    'currentPage' => 1,
                    'totalPages' => 1,
                    'platformEnums' => PlatformEnum::getActivePlatforms(),
                ]);
            }

            // Create a paginated collection with single result
            $games = collect([$game]);

            return view('search.results', [
                'games' => $games,
                'query' => $query,
                'viewMode' => $viewMode,
                'totalResults' => 1,
                'currentPage' => 1,
                'totalPages' => 1,
                'hasMore' => false,
                'platformEnums' => PlatformEnum::getActivePlatforms(),
            ]);
        } catch (\Exception $e) {
            \Log::error('IGDB ID search results exception', [
                'igdb_id' => $igdbId,
                'error' => $e->getMessage()
            ]);

            return view('search.results', [
                'games' => collect(),
                'query' => $query,
                'viewMode' => $viewMode,
                'totalResults' => 0,
                'currentPage' => 1,
                'totalPages' => 1,
                'platformEnums' => PlatformEnum::getActivePlatforms(),
            ]);
        }
    }






    public function searchResults(Request $request): \Illuminate\View\View
    {
        $query = trim($request->query('q', ''));
        $page = (int) $request->query('page', 1);
        $viewMode = $request->query('view', 'grid'); // 'grid' or 'list'
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        if (strlen($query) < 2) {
            return view('search.results', [
                'games' => collect(),
                'query' => $query,
                'viewMode' => $viewMode,
                'totalResults' => 0,
                'currentPage' => 1,
                'totalPages' => 1,
                'platformEnums' => PlatformEnum::getActivePlatforms(),
            ]);
        }

        // Check if query is an IGDB ID (numeric)
        if (is_numeric($query) && ctype_digit($query)) {
            return $this->searchResultsByIgdbId((int)$query, $query, $viewMode);
        }

        try {
            $validPlatformIds = PlatformEnum::getActivePlatforms()
                ->keys()
                ->toArray();

            // Normalize query
            $normalizedQuery = preg_replace('/[:;,\-\.]/', ' ', $query);
            $normalizedQuery = preg_replace('/\s+/', ' ', $normalizedQuery);
            $normalizedQuery = trim($normalizedQuery);

            $words = array_filter(explode(' ', $normalizedQuery), fn($w) => strlen($w) > 0);
            $platformsList = implode(',', $validPlatformIds);

            // Build IGDB query
            $nameConditions = [];
            foreach ($words as $word) {
                $escapedWord = str_replace('"', "'", $word);
                $nameConditions[] = 'name ~ *"' . $escapedWord . '"*';
            }
            $nameWhereClause = implode(' & ', $nameConditions);

            // Main query: games matching name AND platform filter
            $igdbQuery = 'fields name, first_release_date, cover.image_id, platforms.id, platforms.name, game_type, category, collection; ' .
                'where ' . $nameWhereClause . ' ' .
                '& platforms = (' . $platformsList . ') ' .
                '& game_type = (0, 1, 2, 3, 4, 5, 8, 9, 10); ' .
                'sort first_release_date desc; ' .
                'limit ' . $perPage . '; ' .
                'offset ' . $offset . ';';

            // Bundle query (without platform filter)
            $bundleQuery = 'fields name, first_release_date, cover.image_id, platforms.id, platforms.name, game_type, category, collection; ' .
                'where ' . $nameWhereClause . ' ' .
                '& (name ~ *"Bundle"* | name ~ *"Collection"*) ' .
                '& game_type = (3, 5); ' .
                'sort first_release_date desc; ' .
                'limit 10;';

            $response = Http::igdb()
                ->withBody($igdbQuery, 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            $igdbResponseData = $response->json() ?? [];

            // Fetch bundles separately
            $bundleResponse = Http::igdb()
                ->withBody($bundleQuery, 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            $bundleData = $bundleResponse->json() ?? [];

            // Merge results: add bundles at the beginning if they're not already in results
            $existingIds = collect($igdbResponseData)->pluck('id')->toArray();
            $newBundles = collect($bundleData)->filter(fn($bundle) => !in_array($bundle['id'] ?? null, $existingIds))->toArray();

            // Prepend bundles to results
            $igdbResponseData = array_merge($newBundles, $igdbResponseData);

            // Filter out non-array items (safety check)
            $igdbResponseData = array_filter($igdbResponseData, fn($item) => is_array($item));

            // Convert to Game models or fetch/create them
            $games = collect($igdbResponseData)->map(function ($igdbGame) {
                if (!is_array($igdbGame)) {
                    return null;
                }

                $gameType = isset($igdbGame['game_type']) ? (int)$igdbGame['game_type'] : 0;
                $gameName = $igdbGame['name'] ?? 'Unknown Game';

                // Detect bundles by name
                $isBundle = stripos($gameName, 'Bundle') !== false || stripos($gameName, 'Collection') !== false;
                if ($isBundle && $gameType !== 5) {
                    $gameType = 5;
                }

                // Try to find existing game
                $game = Game::with('platforms')->where('igdb_id', $igdbGame['id'])->first();

                if (!$game) {
                    // Create a new game record
                    $game = Game::create([
                        'igdb_id' => $igdbGame['id'],
                        'name' => $gameName,
                        'game_type' => $gameType,
                        'cover_image_id' => $igdbGame['cover']['image_id'] ?? null,
                        'first_release_date' => isset($igdbGame['first_release_date'])
                            ? \Carbon\Carbon::createFromTimestamp($igdbGame['first_release_date'])
                            : null,
                    ]);

                    // Sync platforms
                    if (!empty($igdbGame['platforms'])) {
                        $platformIds = collect($igdbGame['platforms'])->map(function ($platform) {
                            return Platform::firstOrCreate(
                                ['igdb_id' => $platform['id']],
                                ['name' => $platform['name'] ?? 'Unknown']
                            )->id;
                        });
                        $game->platforms()->sync($platformIds);
                        $game->load('platforms');
                    }
                }

                return $game;
            })->filter();

            // For pagination, we'll estimate total pages (IGDB doesn't provide total count easily)
            // We'll show pagination if we got a full page of results
            $hasMore = count($igdbResponseData) >= $perPage;
            $totalPages = $hasMore ? $page + 1 : $page; // Simple estimation

            return view('search.results', [
                'games' => $games,
                'query' => $query,
                'viewMode' => $viewMode,
                'totalResults' => $games->count(),
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'hasMore' => $hasMore,
                'platformEnums' => PlatformEnum::getActivePlatforms(),
            ]);

        } catch (\Exception $e) {
            \Log::error('IGDB search results exception', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('search.results', [
                'games' => collect(),
                'query' => $query,
                'viewMode' => $viewMode,
                'totalResults' => 0,
                'currentPage' => 1,
                'totalPages' => 1,
                'platformEnums' => PlatformEnum::getActivePlatforms(),
                'error' => app()->environment('local') ? $e->getMessage() : 'An error occurred while searching. Please try again.',
            ]);
        }
    }

    // Helper method (optional, to avoid duplication)
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

        // Sync companies (developers and publishers)
        if (!empty($igdbGame['involved_companies'])) {
            $companyData = [];
            foreach ($igdbGame['involved_companies'] as $involvedCompany) {
                $company = $involvedCompany['company'] ?? null;
                if (!$company || empty($company['id'])) {
                    continue;
                }

                $companyModel = \App\Models\Company::firstOrCreate(
                    ['igdb_id' => $company['id']],
                    ['name' => $company['name'] ?? 'Unknown']
                );

                $companyData[$companyModel->id] = [
                    'is_developer' => $involvedCompany['developer'] ?? false,
                    'is_publisher' => $involvedCompany['publisher'] ?? false,
                ];
            }
            $game->companies()->sync($companyData);
        }

        // Sync game engines
        if (!empty($igdbGame['game_engines'])) {
            $ids = collect($igdbGame['game_engines'])->map(fn($engine) =>
            \App\Models\GameEngine::firstOrCreate(
                ['igdb_id' => $engine['id']],
                ['name' => $engine['name'] ?? 'Unknown']
            )->id
            );
            $game->gameEngines()->sync($ids);
        }

        // Sync player perspectives
        if (!empty($igdbGame['player_perspectives'])) {
            $ids = collect($igdbGame['player_perspectives'])->map(fn($perspective) =>
            \App\Models\PlayerPerspective::firstOrCreate(
                ['igdb_id' => $perspective['id']],
                ['name' => $perspective['name'] ?? 'Unknown']
            )->id
            );
            $game->playerPerspectives()->sync($ids);
        }
    }
}
