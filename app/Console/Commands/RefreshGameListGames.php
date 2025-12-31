<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameList;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\Platform;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RefreshGameListGames extends Command
{
    protected $signature = 'igdb:gamelist:refresh
                            {game_list_id : The ID of the game list to refresh}
                            {--force : Force refresh even if games were recently synced}';

    protected $description = 'Refresh all games in a game list by fetching latest data from IGDB';

    public function handle(IgdbService $igdb): int
    {
        $gameListId = $this->argument('game_list_id');
        $force = $this->option('force');

        // Load the game list
        $gameList = GameList::with('games')->find($gameListId);

        if (!$gameList) {
            $this->error("Game list with ID {$gameListId} not found.");
            return self::FAILURE;
        }

        $this->info("Refreshing games in list: {$gameList->name}");
        $this->info("Total games: " . $gameList->games->count());

        if ($gameList->games->isEmpty()) {
            $this->warn('No games found in this list.');
            return self::SUCCESS;
        }

        // Filter games if not forcing refresh
        $gamesToRefresh = $gameList->games;
        if (!$force) {
            $gamesToRefresh = $gamesToRefresh->filter(function ($game) {
                // Skip if synced within last 24 hours
                return !$game->last_igdb_sync_at ||
                       $game->last_igdb_sync_at->lt(now()->subDay());
            });

            $skippedCount = $gameList->games->count() - $gamesToRefresh->count();
            if ($skippedCount > 0) {
                $this->info("Skipping {$skippedCount} recently synced game(s). Use --force to refresh all.");
            }
        }

        if ($gamesToRefresh->isEmpty()) {
            $this->info('All games were recently synced. Use --force to refresh anyway.');
            return self::SUCCESS;
        }

        $this->info("Refreshing " . $gamesToRefresh->count() . " game(s)...");
        $bar = $this->output->createProgressBar($gamesToRefresh->count());
        $bar->start();

        foreach ($gamesToRefresh as $game) {
            if (!$game->igdb_id) {
                $this->newLine();
                $this->warn("Skipping '{$game->name}' - no IGDB ID");
                $bar->advance();
                continue;
            }

            try {
                // Fetch game from IGDB
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
                         where id = {$game->igdb_id}; limit 1;";

                $response = \Illuminate\Support\Facades\Http::igdb()
                    ->withBody($query, 'text/plain')
                    ->post('https://api.igdb.com/v4/games');

                if ($response->failed() || empty($response->json())) {
                    $this->newLine();
                    $this->warn("Could not fetch '{$game->name}' (IGDB ID: {$game->igdb_id})");
                    $bar->advance();
                    continue;
                }

                $rawIgdbResponse = $response->json()[0];
                $igdbGame = $rawIgdbResponse;

                // Enrich with Steam data
                $igdbGame = $igdb->enrichWithSteamData([$igdbGame])[0] ?? $igdbGame;

                $gameName = $igdbGame['name'] ?? $game->name;
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

                // Update game
                $game->update([
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
                    'raw_igdb_json' => $rawIgdbResponse,
                    'steam_data' => $igdbGame['steam'] ?? null,
                    'steam_wishlist_count' => $igdbGame['steam']['wishlist_count'] ?? null,
                    'similar_games' => $igdbGame['similar_games'] ?? null,
                    'screenshots' => $igdbGame['screenshots'] ?? null,
                    'trailers' => $igdbGame['game_videos'] ?? null,
                    'last_igdb_sync_at' => now(),
                ]);

                // Update priority for existing games
                $game->calculateAndSaveUpdatePriority();

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

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to refresh '{$game->name}': " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Game list refresh completed!');

        return self::SUCCESS;
    }
}
