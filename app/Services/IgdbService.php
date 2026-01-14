<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ExternalSourceData;
use App\Enums\PlatformEnum;
use App\Models\ExternalGameSource;
use App\Models\Game;
use App\Models\GameExternalSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IgdbService
{
    // Default platforms: PC (6), PS5 (167), Xbox Series X|S (169), Switch (130)
    // private array $defaultPlatforms = [6, 167, 169, 130];

    public function getAccessToken(): string
    {
        return Cache::remember('igdb_access_token', now()->addHours(23), function () {
            $response = Http::asForm()->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => config('igdb.credentials.client_id'),
                'client_secret' => config('igdb.credentials.client_secret'),
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Failed to obtain IGDB access token: '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Extract external game sources from IGDB response.
     *
     * @param  array  $igdbGame  Raw IGDB game response with external_games expanded
     * @return Collection<ExternalSourceData>
     */
    public function extractExternalSources(array $igdbGame): Collection
    {
        $sources = collect();

        if (empty($igdbGame['external_games']) || ! is_array($igdbGame['external_games'])) {
            return $sources;
        }

        foreach ($igdbGame['external_games'] as $externalGame) {
            if (! is_array($externalGame)) {
                continue;
            }

            // Handle both formats: external_game_source as integer ID or as object
            $externalGameSource = $externalGame['external_game_source'] ?? null;
            $sourceId = is_array($externalGameSource)
                ? ($externalGameSource['id'] ?? null)
                : ($externalGameSource ?? $externalGame['category'] ?? null);

            $sourceName = is_array($externalGameSource)
                ? ($externalGameSource['name'] ?? null)
                : $this->getCategoryName($sourceId);

            $externalUid = $externalGame['uid'] ?? null;
            $externalUrl = $externalGame['url'] ?? null;

            if (! $sourceId || ! $externalUid) {
                continue;
            }

            $sources->push(new ExternalSourceData(
                sourceId: (int) $sourceId,
                sourceName: $sourceName ?? 'Unknown',
                externalUid: (string) $externalUid,
                externalUrl: $externalUrl,
                category: $sourceId,
            ));
        }

        return $sources;
    }

    /**
     * Sync external sources for a game from IGDB data.
     *
     * @param  Game  $game  The game model
     * @param  array  $igdbGame  Raw IGDB game response
     */
    public function syncExternalSources(Game $game, array $igdbGame): void
    {
        $sources = $this->extractExternalSources($igdbGame);

        if ($sources->isEmpty()) {
            return;
        }

        $activeSources = config('services.igdb.active_external_sources', [1]);

        foreach ($sources as $sourceData) {
            // Only sync sources that are configured as active
            if (! in_array($sourceData->sourceId, $activeSources, true)) {
                continue;
            }

            $externalGameSource = ExternalGameSource::where('igdb_id', $sourceData->sourceId)->first();

            if (! $externalGameSource) {
                continue;
            }

            GameExternalSource::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'external_game_source_id' => $externalGameSource->id,
                ],
                [
                    'external_uid' => $sourceData->externalUid,
                    'external_url' => $sourceData->externalUrl,
                ]
            );
        }
    }

    /**
     * Get the Steam AppID for a game from external sources.
     *
     * @param  Game  $game  The game model
     * @return string|null The Steam AppID or null if not found
     */
    public function getSteamAppIdFromSources(Game $game): ?string
    {
        $steamSource = $game->gameExternalSources()
            ->whereHas('externalGameSource', function ($query) {
                $query->where('igdb_id', 1); // Steam
            })
            ->first();

        return $steamSource?->external_uid;
    }

    /**
     * Get category name from IGDB category ID (fallback).
     */
    private function getCategoryName(?int $category): ?string
    {
        return match ($category) {
            1 => 'Steam',
            5 => 'GOG',
            10 => 'YouTube',
            11 => 'Xbox Marketplace',
            13 => 'Apple App Store',
            14 => 'Google Play',
            15 => 'itch.io',
            20 => 'Amazon ASIN',
            22 => 'Twitch',
            23 => 'Android',
            26 => 'Epic Games Store',
            28 => 'Oculus',
            29 => 'Utomik',
            31 => 'Focus Entertainment',
            36 => 'PlayStation Store',
            37 => 'Xbox Game Pass',
            default => null,
        };
    }

    /**
     * Fetch upcoming games from IGDB
     *
     * @param  array  $platformIds  Platform IDs to filter by
     * @param  Carbon|null  $startDate  Start date for filtering
     * @param  Carbon|null  $endDate  End date for filtering
     * @param  int  $limit  Maximum number of games to fetch (IGDB max is 500 per query)
     * @param  int  $offset  Offset for pagination (default: 0)
     * @return array Array of game data
     */
    public function fetchUpcomingGames(
        array $platformIds = [],
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        int $limit = 500,
        int $offset = 0
    ): array {
        $platformIds = empty($platformIds) ? PlatformEnum::getActivePlatformsValues() : $platformIds;
        $startDate ??= Carbon::today();
        $endDate ??= $startDate->copy()->addWeek();

        // IGDB max limit per query is 500
        $queryLimit = min($limit, 500);

        $query = sprintf(
            'fields name, first_release_date, summary, platforms.name, cover.image_id,
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
                     where platforms = (%s) & first_release_date >= %d & first_release_date < %d;
                     sort first_release_date asc;
                     limit %d;
                     offset %d;',
            implode(',', $platformIds),
            $startDate->timestamp,
            $endDate->timestamp,
            $queryLimit,
            $offset
        );

        $response = Http::igdb()
            ->withBody($query, 'text/plain')
            ->post('https://api.igdb.com/v4/games');

        if ($response->failed()) {
            throw new RuntimeException('IGDB API request failed: '.$response->status().' - '.$response->body());
        }

        return $response->json();
    }

    /**
     * Fetch popular upcoming games from Steam (sorted by wishlists)
     * Handles real/current response structure and missing keys safely
     *
     * @deprecated Will be removed in future version.
     *             Steam upcoming games now fetched via SteamSpy.
     * @see \App\Services\SteamSpyService::fetchTop100InTwoWeeks()
     */
    public function fetchSteamPopularUpcoming(int $count = 50): Collection
    {
        $response = Http::timeout(15)
            ->get('https://store.steampowered.com/search/results/', [
                'filter' => 'popularcomingsoon',
                'json' => 1,
                'count' => min($count, 100), // Steam caps at ~100
                'infinite' => 1,
            ]);

        if ($response->failed()) {
            \Log::warning('Steam popular upcoming request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return collect();
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['items'])) {
            return collect();
        }

        return collect($data['items'])->map(function ($item) {
            // Use null coalescing and safe access for all keys
            return [
                'appid' => $item['id'] ?? null,                    // Key is 'id', not 'appid'
                'name' => $item['name'] ?? 'Unknown Game',
                'release_string' => $item['release_string'] ?? null,        // e.g., "Coming Soon", "Q1 2026"
                'release_date' => $item['release_date'] ?? null,          // Sometimes present as timestamp
                'wishlist_count' => $item['wishlist_count'] ?? null,        // Approximate wishlists
                'header_image' => $item['header'] ?? null,                // Often 'header' instead of 'header_image'
                'capsule_image' => $item['capsule'] ?? null,
                'tiny_image' => $item['tiny_image'] ?? null,
                'discount' => $item['discount'] ?? false,
                'discounted_price' => $item['discounted_price'] ?? null,
                'original_price' => $item['price'] ?? null,
                'reviews' => $item['reviews'] ?? null,               // e.g., "Overwhelmingly Positive"
            ];
        })->filter(fn ($game) => ! empty($game['appid'])); // Remove any malformed entries
    }

    /**
     * Get detailed Steam data for a list of AppIDs
     *
     * @deprecated Will be removed in future version.
     *             Direct Steam API access replaced by SteamSpy integration.
     * @see \App\Services\SteamSpyService::fetchGameDetails()
     */
    public function getSteamAppDetails(array $appIds): array
    {
        if (empty($appIds)) {
            return [];
        }

        $appIdsStr = implode(',', $appIds);

        $response = Http::get('https://store.steampowered.com/api/appdetails', [
            'appids' => $appIdsStr,
            'filters' => 'name,release_date,header_image,platforms,price_overview',
            'cc' => 'us', // Change if needed
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Steam appdetails request failed');
        }

        $data = $response->json();

        $details = [];
        foreach ($data as $appid => $info) {
            if ($info['success'] ?? false) {
                $details[$appid] = $info['data'];
            }
        }

        return $details;
    }

    /**
     * Enrich IGDB games with Steam data (for PC games)
     *
     * @deprecated Will be removed in future version.
     *             Steam data enrichment has been replaced by separate SteamSpy sync.
     *             This method now returns the input unchanged.
     * @see \App\Services\SteamSpyService::fetchGameDetails()
     * @see \App\Console\Commands\SteamSpySync
     */
    public function enrichWithSteamData(array $igdbGames): array
    {
        // DEPRECATED: Return input unchanged to avoid performance impact
        // Steam data is now fetched separately via SteamSpy
        return $igdbGames;
    }

    /**
     * Helper: Generate cover URL
     */
    public function getCoverUrl(?string $imageId, string $size = 'cover_big'): string
    {
        if (! $imageId) {
            return 'https://via.placeholder.com/300x400?text=No+Cover';
        }

        // Available sizes: thumb, cover_small, cover_big, logo_med, screenshot_med, etc.
        return "https://images.igdb.com/igdb/image/upload/t_{$size}/{$imageId}.jpg";
    }

    public function getScreenshotUrl(?string $imageId, string $size = 'screenshot_big'): string
    {
        if (! $imageId) {
            return 'https://via.placeholder.com/1280x720?text=No+Screenshot';
        }

        return "https://images.igdb.com/igdb/image/upload/t_{$size}/{$imageId}.jpg";
    }

    public function getYouTubeEmbedUrl(?string $videoId): string
    {
        if (! $videoId) {
            return '';
        }

        return "https://www.youtube.com/embed/{$videoId}?rel=0";
    }

    public function getYouTubeThumbnailUrl(?string $videoId): string
    {
        if (! $videoId) {
            return 'https://via.placeholder.com/1280x720?text=No+Trailer';
        }

        return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
    }

    /**
     * Fetch game image from SteamGridDB when IGDB doesn't provide one
     *
     * @param  string  $gameName  The game name to search for
     * @param  string  $type  Image type: 'cover', 'hero', or 'logo'
     * @param  int|null  $steamAppId  Optional Steam AppID for better matching
     * @param  int|null  $igdbId  Optional IGDB ID for filename
     * @return string|null Filename of downloaded image, or null if failed
     */
    public function fetchImageFromSteamGridDb(string $gameName, string $type = 'cover', ?int $steamAppId = null, ?int $igdbId = null): ?string
    {
        $apiKey = config('services.steamgriddb.api_key');
        if (! $apiKey) {
            \Log::warning('SteamGridDB API key not configured');

            return null;
        }

        try {
            // Search for game
            $searchResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])
                ->timeout(10)
                ->get('https://www.steamgriddb.com/api/v2/search/autocomplete/'.urlencode($gameName));

            if ($searchResponse->failed() || empty($searchResponse->json()['data'])) {
                return null;
            }

            $searchResults = $searchResponse->json()['data'] ?? [];
            if (empty($searchResults)) {
                return null;
            }

            // If Steam AppID provided, try to find exact match
            $gameId = null;
            if ($steamAppId) {
                foreach ($searchResults as $result) {
                    if (isset($result['types']) && in_array('steam', $result['types'] ?? [])) {
                        // Try to match by Steam AppID if available in result
                        if (isset($result['id'])) {
                            $gameId = $result['id'];
                            break;
                        }
                    }
                }
            }

            // Fallback to first result if no AppID match
            if (! $gameId && ! empty($searchResults[0]['id'])) {
                $gameId = $searchResults[0]['id'];
            }

            if (! $gameId) {
                return null;
            }

            // Fetch grids (covers)
            $gridsResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])
                ->timeout(10)
                ->get("https://www.steamgriddb.com/api/v2/grids/game/{$gameId}");

            if ($gridsResponse->failed() || empty($gridsResponse->json()['data'])) {
                return null;
            }

            $grids = $gridsResponse->json()['data'] ?? [];
            if (empty($grids)) {
                return null;
            }

            // Select grid based on requested type
            $selectedGrid = match ($type) {
                'hero' => $this->selectHeroGrid($grids),
                'logo' => $this->selectLogoGrid($grids),
                'cover' => $this->selectCoverGrid($grids),
                default => $grids[0] ?? null,
            };

            if (! $selectedGrid && ! empty($grids)) {
                $selectedGrid = $grids[0];
            }

            $imageUrl = $selectedGrid['url'] ?? null;
            if (! $imageUrl) {
                return null;
            }

            // Download and store the image
            $imageResponse = Http::timeout(15)->get($imageUrl);
            if ($imageResponse->failed()) {
                \Log::warning("Failed to download SteamGridDB cover image: {$imageUrl}");

                return null;
            }

            // Ensure storage directory exists
            $storagePath = storage_path('app/public/covers');
            if (! File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            // Generate filename
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = ($igdbId ?: ($steamAppId ?: 'game')).'_'.time().'.'.$extension;
            $filePath = $storagePath.'/'.$filename;

            // Save the image
            File::put($filePath, $imageResponse->body());

            \Log::info("Downloaded SteamGridDB {$type} image for {$gameName}: {$filename}");

            return $filename;
        } catch (\Exception $e) {
            \Log::warning("SteamGridDB {$type} image fetch exception for {$gameName}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch game cover from SteamGridDB (backward compatibility)
     *
     * @param  string  $gameName  The game name to search for
     * @param  int|null  $steamAppId  Optional Steam AppID for better matching
     * @param  int|null  $igdbId  Optional IGDB ID for filename
     * @return string|null Filename of downloaded cover, or null if failed
     */
    public function fetchCoverFromSteamGridDb(string $gameName, ?int $steamAppId = null, ?int $igdbId = null): ?string
    {
        return $this->fetchImageFromSteamGridDb($gameName, 'cover', $steamAppId, $igdbId);
    }

    /**
     * Select hero grid from SteamGridDB grids
     */
    private function selectHeroGrid(array $grids): ?array
    {
        // Prefer 'hero' style, fallback to 'alternate', then first available
        foreach ($grids as $grid) {
            if (isset($grid['style']) && $grid['style'] === 'hero') {
                return $grid;
            }
        }

        foreach ($grids as $grid) {
            if (isset($grid['style']) && $grid['style'] === 'alternate') {
                return $grid;
            }
        }

        return null;
    }

    /**
     * Select logo grid from SteamGridDB grids
     */
    private function selectLogoGrid(array $grids): ?array
    {
        // Prefer 'logo' style
        foreach ($grids as $grid) {
            if (isset($grid['style']) && $grid['style'] === 'logo') {
                return $grid;
            }
        }

        return null;
    }

    /**
     * Select cover grid from SteamGridDB grids
     */
    private function selectCoverGrid(array $grids): ?array
    {
        // Prefer 'alternate' style, fallback to first available
        foreach ($grids as $grid) {
            if (isset($grid['style']) && $grid['style'] === 'alternate') {
                return $grid;
            }
        }

        return null;
    }
}
