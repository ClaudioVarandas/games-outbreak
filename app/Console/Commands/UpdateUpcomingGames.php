<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\Platform;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use View;

class UpdateUpcomingGames extends Command
{
    protected $signature = 'igdb:upcoming:update
                            {--days=14 : Number of days ahead to fetch (default 14)}
                            {--start-date= : Start date (Y-m-d format, defaults to today)}
                            {--platforms= : Comma-separated IGDB platform IDs (e.g. 6,167,169,130)}
                            {--limit=500 : Max games to fetch (default: 500, IGDB max per query)}
                            {--igdb-id= : Fetch a single game by IGDB ID}';

    protected $description = 'Fetch upcoming games from IGDB, enrich with Steam data, and store in local database';

    public function handle(IgdbService $igdb): int
    {
        $igdbId = $this->option('igdb-id');
        
        // If --igdb-id is provided, fetch only that game
        if ($igdbId) {
            $igdbId = (int) $igdbId;
            $this->info("Fetching game with IGDB ID: {$igdbId}...");
            
            try {
                $query = "fields name, first_release_date, summary, platforms.name, platforms.id, cover.image_id,
                             genres.name, genres.id,
                             game_modes.name, game_modes.id,
                             similar_games.name, similar_games.cover.image_id, similar_games.id,
                             screenshots.image_id,
                             videos.video_id,
                             external_games.category, external_games.uid,
                             websites.category, websites.url, game_type,
                             release_dates.platform, release_dates.date, release_dates.region, release_dates.human, release_dates.y, release_dates.m, release_dates.d,
                             involved_companies.company.id, involved_companies.company.name, involved_companies.developer, involved_companies.publisher,
                             game_engines.name, game_engines.id,
                             player_perspectives.name, player_perspectives.id;
                         where id = {$igdbId}; limit 1;";

                $response = \Illuminate\Support\Facades\Http::igdb()
                    ->withBody($query, 'text/plain')
                    ->post('https://api.igdb.com/v4/games');

                if ($response->failed() || empty($response->json())) {
                    $this->error("Game with IGDB ID {$igdbId} not found.");
                    return self::FAILURE;
                }

                $rawIgdbResponse = $response->json()[0];
                $igdbGame = $rawIgdbResponse;

                // Enrich with Steam data
                $igdbGame = $igdb->enrichWithSteamData([$igdbGame])[0] ?? $igdbGame;
                $games = [$igdbGame];
                
                // Store raw JSON before enrichment
                $rawJson = $rawIgdbResponse;
            } catch (\Exception $e) {
                $this->error('Failed to fetch game: ' . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            // Normal flow: fetch upcoming games
            $days = max(1, (int) $this->option('days'));
            $limit = (int) $this->option('limit');
            $platformIds = $this->option('platforms')
                ? array_map('intval', explode(',', $this->option('platforms')))
                : [];

            $startDateInput = $this->option('start-date');
            if ($startDateInput) {
                try {
                    $startDate = Carbon::createFromFormat('Y-m-d', $startDateInput)->startOfDay();
                } catch (\Exception $e) {
                    $this->error("Invalid start-date format. Use Y-m-d format (e.g., 2024-01-15)");
                    return self::FAILURE;
                }
            } else {
                $startDate = Carbon::today();
            }
            
            $endDate = $startDate->copy()->addDays($days);

            $this->info("Fetching upcoming games from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}...");
            if (!empty($platformIds)) {
                $this->info('Platforms: ' . implode(', ', $platformIds));
            }
            $this->info("Limit: {$limit} games");

            try {
                $games = [];
                $igdbMaxPerQuery = 500;
                $offset = 0;
                $totalFetched = 0;

                // If limit > 500, we need pagination
                while ($totalFetched < $limit) {
                    $remaining = $limit - $totalFetched;
                    $currentLimit = min($remaining, $igdbMaxPerQuery);
                    
                    $this->info("Fetching batch: offset {$offset}, limit {$currentLimit}...");
                    
                    $batchGames = $igdb->fetchUpcomingGames(
                        platformIds: $platformIds,
                        startDate: $startDate,
                        endDate: $endDate,
                        limit: $currentLimit,
                        offset: $offset
                    );

                    if (empty($batchGames)) {
                        // No more games available
                        break;
                    }

                    $games = array_merge($games, $batchGames);
                    $totalFetched += count($batchGames);
                    $offset += $currentLimit;

                    // If we got fewer games than requested, we've reached the end
                    if (count($batchGames) < $currentLimit) {
                        break;
                    }

                    // Small delay between pagination requests to avoid rate limiting
                    if ($totalFetched < $limit) {
                        usleep(500000); // 0.5 seconds
                    }
                }

                $this->info("Fetched {$totalFetched} game(s) from IGDB");

                $games = $igdb->enrichWithSteamData($games);
                $rawJson = null; // Not storing raw JSON for bulk fetches
            } catch (\Exception $e) {
                $this->error('Failed to fetch or enrich games: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        if (empty($games)) {
            $this->warn('No games found.');
            return self::SUCCESS;
        }

        $this->info("Processing " . count($games) . " game(s)...");

        $bar = $this->output->createProgressBar(count($games));
        $bar->start();

        foreach ($games as $index => $igdbGame) {
            // Get raw JSON for this game (only if fetching single game)
            $gameRawJson = ($igdbId && isset($rawJson)) ? $rawJson : null;
            
            $gameName = $igdbGame['name'] ?? 'Unknown Game';
            $steamAppId = $igdbGame['steam']['appid'] ?? null;
            $igdbGameId = $igdbGame['id'] ?? null;

            // Store IGDB cover.image_id in cover_image_id
            $coverImageId = $igdbGame['cover']['image_id'] ?? null;
            
            // If IGDB didn't provide a cover, try SteamGridDB
            if (!$coverImageId) {
                $this->line("No IGDB cover for {$gameName}, trying SteamGridDB...");
                $steamGridDbCover = $igdb->fetchImageFromSteamGridDb($gameName, 'cover', $steamAppId, $igdbGameId);
                
                if ($steamGridDbCover) {
                    $coverImageId = $steamGridDbCover;
                    $this->info("  ✓ Found SteamGridDB cover: {$steamGridDbCover}");
                } else {
                    $this->warn("  ✗ No SteamGridDB cover found");
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
            
            $game = Game::updateOrCreate(
                ['igdb_id' => $igdbGame['id']],
                [
                    'name' => $gameName,
                    'summary' => $igdbGame['summary'] ?? null,
                    'first_release_date' => isset($igdbGame['first_release_date'])
                        ? Carbon::createFromTimestamp($igdbGame['first_release_date'])
                        : null,
                    'cover_image_id' => $coverImageId,
                    'hero_image_id' => $heroImageId,
                    'logo_image_id' => $logoImageId,
                    'game_type' => $igdbGame['game_type'] ?? 0,
                    'release_dates' => \App\Models\Game::transformReleaseDates($igdbGame['release_dates'] ?? null),
                    'raw_igdb_json' => $gameRawJson,
                    'steam_data' => $igdbGame['steam'] ?? null,
                    'steam_wishlist_count' => $igdbGame['steam']['wishlist_count'] ?? null,
                    'similar_games' => $igdbGame['similar_games'] ?? null,
                    'screenshots' => $igdbGame['screenshots'] ?? null,
                    'trailers' => $igdbGame['game_videos'] ?? null,
                ]
            );

            // Sync platforms
            if (!empty($igdbGame['platforms'])) {
                $platformModelIds = collect($igdbGame['platforms'])->map(function ($plat) {
                    return Platform::firstOrCreate(
                        ['igdb_id' => $plat['id']],
                        ['name' => $plat['name'] ?? 'Unknown Platform']
                    )->id;
                })->all();

                $game->platforms()->sync($platformModelIds);
            }

            // Sync genres
            if (!empty($igdbGame['genres'])) {
                $genreIds = collect($igdbGame['genres'])->map(function ($genre) {
                    return Genre::firstOrCreate(
                        ['igdb_id' => $genre['id']],
                        ['name' => $genre['name'] ?? 'Unknown Genre']
                    )->id;
                })->all();

                $game->genres()->sync($genreIds);
            }

            // Sync game modes
            if (!empty($igdbGame['game_modes'])) {
                $modeIds = collect($igdbGame['game_modes'])->map(function ($mode) {
                    return GameMode::firstOrCreate(
                        ['igdb_id' => $mode['id']],
                        ['name' => $mode['name'] ?? 'Unknown Mode']
                    )->id;
                })->all();

                $game->gameModes()->sync($modeIds);
            }

            // Sync companies (developers/publishers)
            if (!empty($igdbGame['involved_companies'])) {
                $syncData = [];
                foreach ($igdbGame['involved_companies'] as $involvedCompany) {
                    if (empty($involvedCompany['company'])) {
                        continue;
                    }
                    
                    $company = \App\Models\Company::firstOrCreate(
                        ['igdb_id' => $involvedCompany['company']['id']],
                        ['name' => $involvedCompany['company']['name'] ?? 'Unknown']
                    );
                    
                    $syncData[$company->id] = [
                        'is_developer' => $involvedCompany['developer'] ?? false,
                        'is_publisher' => $involvedCompany['publisher'] ?? false,
                    ];
                }
                $game->companies()->sync($syncData);
            }

            // Sync game engines
            if (!empty($igdbGame['game_engines'])) {
                $engineIds = collect($igdbGame['game_engines'])->map(function ($engine) {
                    return \App\Models\GameEngine::firstOrCreate(
                        ['igdb_id' => $engine['id']],
                        ['name' => $engine['name'] ?? 'Unknown']
                    )->id;
                })->all();

                $game->gameEngines()->sync($engineIds);
            }

            // Sync player perspectives
            if (!empty($igdbGame['player_perspectives'])) {
                $perspectiveIds = collect($igdbGame['player_perspectives'])->map(function ($perspective) {
                    return \App\Models\PlayerPerspective::firstOrCreate(
                        ['igdb_id' => $perspective['id']],
                        ['name' => $perspective['name'] ?? 'Unknown']
                    )->id;
                })->all();

                $game->playerPerspectives()->sync($perspectiveIds);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Upcoming games updated successfully!');

        return self::SUCCESS;
    }

}
