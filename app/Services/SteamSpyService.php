<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameExternalSource;
use App\Models\SteamGameData;
use Illuminate\Support\Facades\Http;

class SteamSpyService
{
    private const BASE_URL = 'https://steamspy.com/api.php';

    private const RATE_LIMIT_DELAY_MS = 250000; // 250ms = ~4 req/sec

    private const HIGH_PRIORITY_THRESHOLD = 50;

    private const HIGH_PRIORITY_STALE_DAYS = 7;

    private const LOW_PRIORITY_STALE_DAYS = 30;

    private const RECENTLY_RELEASED_DAYS = 14;

    private const RECENTLY_RELEASED_STALE_DAYS = 3;

    public function fetchGameDetails(string $appId): ?array
    {
        usleep(self::RATE_LIMIT_DELAY_MS);

        try {
            $response = Http::timeout(15)
                ->get(self::BASE_URL, [
                    'request' => 'appdetails',
                    'appid' => $appId,
                ]);

            if ($response->failed()) {
                \Log::warning('SteamSpy API request failed', [
                    'appid' => $appId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            if (empty($data) || isset($data['error'])) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('SteamSpy API exception', [
                'appid' => $appId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fetchTop100InTwoWeeks(): array
    {
        usleep(self::RATE_LIMIT_DELAY_MS);

        try {
            $response = Http::timeout(30)
                ->get(self::BASE_URL, [
                    'request' => 'top100in2weeks',
                ]);

            if ($response->failed()) {
                \Log::warning('SteamSpy top100 request failed');

                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            \Log::error('SteamSpy top100 exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function fetchAllGames(int $page = 0): array
    {
        usleep(self::RATE_LIMIT_DELAY_MS);

        try {
            $response = Http::timeout(60)
                ->get(self::BASE_URL, [
                    'request' => 'all',
                    'page' => $page,
                ]);

            if ($response->failed()) {
                \Log::warning('SteamSpy all games request failed', ['page' => $page]);

                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            \Log::error('SteamSpy all games exception', [
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function isStale(Game $game, GameExternalSource $sourceLink): bool
    {
        // Never synced = always stale
        if (! $sourceLink->last_synced_at) {
            return true;
        }

        $releaseDate = $game->first_release_date;
        $lastSyncedAt = $sourceLink->last_synced_at;
        $now = now();

        // Synced before release AND game is now released = stale (need post-release data)
        if ($releaseDate && $lastSyncedAt->lt($releaseDate) && $releaseDate->lte($now)) {
            return true;
        }

        $daysSinceSync = $lastSyncedAt->diffInDays($now);

        // Recently released games (last 14 days) need more frequent syncs
        if ($releaseDate && $releaseDate->diffInDays($now) <= self::RECENTLY_RELEASED_DAYS) {
            return $daysSinceSync >= self::RECENTLY_RELEASED_STALE_DAYS;
        }

        // Standard stale check based on priority
        $isHighPriority = ($game->update_priority ?? 0) >= self::HIGH_PRIORITY_THRESHOLD;
        $staleDays = $isHighPriority
            ? self::HIGH_PRIORITY_STALE_DAYS
            : self::LOW_PRIORITY_STALE_DAYS;

        return $daysSinceSync >= $staleDays;
    }

    public function syncGameData(GameExternalSource $sourceLink): bool
    {
        $steamAppId = $sourceLink->external_uid;
        $game = $sourceLink->game;

        if (! $steamAppId || ! $game) {
            $sourceLink->markAsFailed();

            return false;
        }

        $data = $this->fetchGameDetails($steamAppId);

        if (! $data) {
            $sourceLink->markAsFailed();

            return false;
        }

        SteamGameData::updateOrCreate(
            ['game_id' => $game->id],
            [
                'steam_app_id' => $steamAppId,
                'owners' => $data['owners'] ?? null,
                'players_forever' => $data['players_forever'] ?? null,
                'players_2weeks' => $data['players_2weeks'] ?? null,
                'average_forever' => $data['average_forever'] ?? null,
                'average_2weeks' => $data['average_2weeks'] ?? null,
                'median_forever' => $data['median_forever'] ?? null,
                'median_2weeks' => $data['median_2weeks'] ?? null,
                'ccu' => $data['ccu'] ?? null,
                'price' => $data['price'] ?? null,
                'score_rank' => ! empty($data['score_rank']) ? (int) $data['score_rank'] : null,
                'genre' => $data['genre'] ?? null,
                'tags' => $data['tags'] ?? null,
            ]
        );

        $sourceLink->markAsSynced();

        \Log::info('SteamSpy: Synced game data', [
            'game_id' => $game->id,
            'steam_app_id' => $steamAppId,
            'owners' => $data['owners'] ?? 'N/A',
        ]);

        return true;
    }
}
