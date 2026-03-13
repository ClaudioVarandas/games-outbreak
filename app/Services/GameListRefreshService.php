<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Game;
use App\Models\GameEngine;
use App\Models\GameList;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\PlayerPerspective;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GameListRefreshService
{
    public function __construct(public IgdbService $igdb) {}

    public function refreshList(GameList $gameList, bool $force = false): void
    {
        $gamesToRefresh = $gameList->games;

        if (! $force) {
            $gamesToRefresh = $gamesToRefresh->filter(function ($game) {
                return ! $game->last_igdb_sync_at ||
                       $game->last_igdb_sync_at->lt(now()->subDay());
            });
        }

        if ($gamesToRefresh->isEmpty()) {
            return;
        }

        foreach ($gamesToRefresh as $game) {
            if (! $game->igdb_id) {
                Log::warning("GameListRefreshService: Skipping '{$game->name}' - no IGDB ID");

                continue;
            }

            try {
                $query = "fields name, first_release_date, summary, platforms.name, platforms.id, cover.image_id,
                             genres.name, genres.id,
                             game_modes.name, game_modes.id,
                             similar_games.name, similar_games.cover.image_id, similar_games.id,
                             screenshots.image_id,
                             videos.video_id,
                             external_games.external_game_source, external_games.uid, external_games.url,
                             websites.category, websites.url, game_type,
                             release_dates.platform, release_dates.date, release_dates.region, release_dates.human, release_dates.y, release_dates.m, release_dates.d, release_dates.status,
                             involved_companies.company.id, involved_companies.company.name, involved_companies.developer, involved_companies.publisher,
                             game_engines.name, game_engines.id,
                             player_perspectives.name, player_perspectives.id;
                         where id = {$game->igdb_id}; limit 1;";

                $response = Http::igdb()
                    ->withBody($query, 'text/plain')
                    ->post('https://api.igdb.com/v4/games');

                if ($response->failed() || empty($response->json())) {
                    Log::warning("GameListRefreshService: Could not fetch '{$game->name}' (IGDB ID: {$game->igdb_id})");

                    continue;
                }

                $rawIgdbResponse = $response->json()[0];
                $igdbGame = $rawIgdbResponse;

                $gameName = $igdbGame['name'] ?? $game->name;
                $igdbGameId = $igdbGame['id'] ?? null;

                $steamAppId = null;
                if (! empty($igdbGame['external_games'])) {
                    foreach ($igdbGame['external_games'] as $ext) {
                        if (($ext['category'] ?? null) === 1 && ! empty($ext['uid'])) {
                            $steamAppId = (int) $ext['uid'];
                            break;
                        }
                    }
                }

                $coverImageId = $igdbGame['cover']['image_id'] ?? null;
                if (! $coverImageId) {
                    $steamGridDbCover = $this->igdb->fetchImageFromSteamGridDb($gameName, 'cover', $steamAppId, $igdbGameId);
                    if ($steamGridDbCover) {
                        $coverImageId = $steamGridDbCover;
                    }
                }

                $heroImageId = $igdbGame['cover']['image_id'] ?? null;
                if (! $heroImageId) {
                    $steamGridDbHero = $this->igdb->fetchImageFromSteamGridDb($gameName, 'hero', $steamAppId, $igdbGameId);
                    if ($steamGridDbHero) {
                        $heroImageId = $steamGridDbHero;
                    }
                }

                $logoImageId = $this->igdb->fetchImageFromSteamGridDb($gameName, 'logo', $steamAppId, $igdbGameId);

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
                    'raw_igdb_json' => $rawIgdbResponse,
                    'similar_games' => $igdbGame['similar_games'] ?? null,
                    'screenshots' => $igdbGame['screenshots'] ?? null,
                    'trailers' => $igdbGame['game_videos'] ?? null,
                    'last_igdb_sync_at' => now(),
                ]);

                Game::syncReleaseDates($game, $igdbGame['release_dates'] ?? null);
                $game->calculateAndSaveUpdatePriority();

                if (! empty($igdbGame['platforms'])) {
                    $platformModelIds = collect($igdbGame['platforms'])->map(function ($plat) {
                        return Platform::firstOrCreate(
                            ['igdb_id' => $plat['id']],
                            ['name' => $plat['name'] ?? 'Unknown Platform']
                        )->id;
                    })->all();

                    $game->platforms()->sync($platformModelIds);
                }

                if (! empty($igdbGame['genres'])) {
                    $genreIds = collect($igdbGame['genres'])->map(function ($genre) {
                        return Genre::firstOrCreate(
                            ['igdb_id' => $genre['id']],
                            ['name' => $genre['name'] ?? 'Unknown Genre']
                        )->id;
                    })->all();

                    $game->genres()->sync($genreIds);
                }

                if (! empty($igdbGame['game_modes'])) {
                    $modeIds = collect($igdbGame['game_modes'])->map(function ($mode) {
                        return GameMode::firstOrCreate(
                            ['igdb_id' => $mode['id']],
                            ['name' => $mode['name'] ?? 'Unknown Mode']
                        )->id;
                    })->all();

                    $game->gameModes()->sync($modeIds);
                }

                if (! empty($igdbGame['involved_companies'])) {
                    $syncData = [];
                    foreach ($igdbGame['involved_companies'] as $involvedCompany) {
                        if (empty($involvedCompany['company'])) {
                            continue;
                        }

                        $company = Company::firstOrCreate(
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

                if (! empty($igdbGame['game_engines'])) {
                    $engineIds = collect($igdbGame['game_engines'])->map(function ($engine) {
                        return GameEngine::firstOrCreate(
                            ['igdb_id' => $engine['id']],
                            ['name' => $engine['name'] ?? 'Unknown']
                        )->id;
                    })->all();

                    $game->gameEngines()->sync($engineIds);
                }

                if (! empty($igdbGame['player_perspectives'])) {
                    $perspectiveIds = collect($igdbGame['player_perspectives'])->map(function ($perspective) {
                        return PlayerPerspective::firstOrCreate(
                            ['igdb_id' => $perspective['id']],
                            ['name' => $perspective['name'] ?? 'Unknown']
                        )->id;
                    })->all();

                    $game->playerPerspectives()->sync($perspectiveIds);
                }

                $this->igdb->syncExternalSources($game, $igdbGame);

            } catch (\Exception $e) {
                Log::error("GameListRefreshService: Failed to refresh '{$game->name}': ".$e->getMessage());
            }
        }
    }
}
