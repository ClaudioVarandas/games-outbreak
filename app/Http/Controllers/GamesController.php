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
    public function upcoming(): View
    {
        $today = Carbon::today();
        $oneMonthFromNow = $today->copy()->addMonth();

        $games = Game::with('platforms')
            ->whereNotNull('first_release_date')
            ->where('first_release_date', '>=', $today)
            ->where('first_release_date', '<=', $oneMonthFromNow)
            ->orderBy('first_release_date')
            ->paginate(24);

        // Pre-load enum data for platforms (avoids N+1 in Blade)
        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('upcoming.index', compact('games', 'platformEnums'));
    }

    public function show($igdbId, IgdbService $igdb): View
    {
        // Try to find existing game
        $game = Game::with(['platforms', 'genres', 'gameModes'])
            ->where('igdb_id', $igdbId)
            ->first();

        // If exists → show it
        if ($game) {
            $platformEnums = PlatformEnum::getActivePlatforms();
            return view('games.show', compact('game', 'platformEnums'));
        }

        // Not in DB → fetch on-demand
        try {
            $query = "fields name, first_release_date, summary, platforms.id, platforms.name, cover.image_id,
                         genres.name, genres.id, game_modes.name, game_modes.id,
                         screenshots.image_id, videos.video_id,
                         external_games.category, external_games.uid,
                         websites.category, websites.url,
                         similar_games.name, similar_games.cover.image_id, similar_games.id, game_type,
                         release_dates.platform, release_dates.date, release_dates.region, release_dates.human, release_dates.y, release_dates.m, release_dates.d;
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
            
            // If IGDB didn't provide a cover, try SteamGridDB
            if (!$coverImageId) {
                $steamGridDbCover = $igdb->fetchImageFromSteamGridDb($gameName, 'cover', $steamAppId, $igdbGameId);
                if ($steamGridDbCover) {
                    $coverImageId = $steamGridDbCover;
                }
            }

            // For hero: Use IGDB cover if available, else fetch from SteamGridDB
            $heroImageId = $igdbGame['cover']['image_id'] ?? null;
            if (!$heroImageId) {
                $steamGridDbHero = $igdb->fetchImageFromSteamGridDb($gameName, 'hero', $steamAppId, $igdbGameId);
                if ($steamGridDbHero) {
                    $heroImageId = $steamGridDbHero;
                }
            }

            // For logo: Fetch from SteamGridDB
            $logoImageId = $igdb->fetchImageFromSteamGridDb($gameName, 'logo', $steamAppId, $igdbGameId);

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
                'release_dates' => Game::transformReleaseDates($igdbGame['release_dates'] ?? null),
                'steam_data' => $igdbGame['steam'] ?? null,
                'screenshots' => $igdbGame['screenshots'] ?? null,
                'trailers' => $igdbGame['videos'] ?? null,
                'similar_games' => $igdbGame['similar_games'] ?? null,
            ]);

            // Sync relations
            $this->syncRelations($game, $igdbGame);

            $game->load(['platforms', 'genres', 'gameModes']);
            $platformEnums = PlatformEnum::getActivePlatforms();

            return view('games.show', compact('game', 'platformEnums'));

        } catch (\Exception $e) {
            \Log::error("On-demand fetch failed for IGDB ID {$igdbId}", ['error' => $e->getMessage()]);
            abort(404, 'Game temporarily unavailable');
        }
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

                return [
                    'igdb_id' => $game['id'],
                    'name' => $gameName,
                        'release' => isset($game['first_release_date'])
                        ? \Carbon\Carbon::createFromTimestamp($game['first_release_date'])->format('d/m/Y')
                        : 'TBA',
                    'cover_url' => isset($game['cover']['image_id'])
                        ? "https://images.igdb.com/igdb/image/upload/t_cover_small/{$game['cover']['image_id']}.jpg"
                        : 'https://via.placeholder.com/90x120/1f2937/6b7280?text=No+Cover',
                    'platforms' => collect($game['platforms'] ?? [])
                        ->map(function ($platform) {
                            $platformEnum = PlatformEnum::fromIgdbId($platform['id'] ?? 0);
                            return $platformEnum?->label() ?? $platform['name'] ?? 'Unknown';
                        })
                        ->take(2)
                        ->implode(', '),
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

            // Convert to Game models or fetch/create them
            $games = collect($igdbResponseData)->map(function ($igdbGame) {
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
            });

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
    }
}
