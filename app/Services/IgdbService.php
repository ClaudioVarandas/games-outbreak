<?php
declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IgdbService
{
    // Default platforms: PC (6), PS5 (167), Xbox Series X|S (169), Switch (130)
    private array $defaultPlatforms = [6, 167, 169, 130];

    public function getAccessToken(): string
    {
        return Cache::remember('igdb_access_token', now()->addHours(23), function () {
            $response = Http::asForm()->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => config('igdb.credentials.client_id'),
                'client_secret' => config('igdb.credentials.client_secret'),
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Failed to obtain IGDB access token: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Fetch upcoming games from IGDB
     * 
     * @param array $platformIds Platform IDs to filter by
     * @param Carbon|null $startDate Start date for filtering
     * @param Carbon|null $endDate End date for filtering
     * @param int $limit Maximum number of games to fetch (IGDB max is 500 per query)
     * @param int $offset Offset for pagination (default: 0)
     * @return array Array of game data
     */
    public function fetchUpcomingGames(
        array   $platformIds = [],
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        int     $limit = 500,
        int     $offset = 0
    ): array
    {
        $platformIds = empty($platformIds) ? $this->defaultPlatforms : $platformIds;
        $startDate ??= Carbon::today();
        $endDate ??= $startDate->copy()->addWeek();

        // IGDB max limit per query is 500
        $queryLimit = min($limit, 500);

        $query = sprintf(
            "fields name, first_release_date, summary, platforms.name, cover.image_id,
                            genres.name, genres.id,
                            game_modes.name, game_modes.id,
                            similar_games.name, similar_games.cover.image_id, similar_games.id,
                            screenshots.image_id,
                            videos.video_id,
                            external_games.category, external_games.uid,
                            websites.category, websites.url, game_type,
                            release_dates.platform, release_dates.date, release_dates.region, release_dates.human, release_dates.y, release_dates.m, release_dates.d;
                     where platforms = (%s) & first_release_date >= %d & first_release_date < %d;
                     sort first_release_date asc;
                     limit %d;
                     offset %d;",
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
            throw new RuntimeException('IGDB API request failed: ' . $response->status() . ' - ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Fetch popular upcoming games from Steam (sorted by wishlists)
     * Handles real/current response structure and missing keys safely
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

        if (!is_array($data) || empty($data['items'])) {
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
        })->filter(fn($game) => !empty($game['appid'])); // Remove any malformed entries
    }

    /**
     * Get detailed Steam data for a list of AppIDs
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
     * This method:
     * - Looks for Steam AppID in 'external_games' (preferred, category 1 = Steam)
     * - Falls back to 'websites' (category 13 = Steam store page)
     * - Fetches detailed Steam data in batch
     * - Attaches relevant Steam info to each matching game
     */
    public function enrichWithSteamData(array $igdbGames): array
    {
        if (empty($igdbGames)) {
            return $igdbGames;
        }

        $gameAppIdMap = []; // [igdb_index => appid]

        foreach ($igdbGames as $index => $game) {
            $appId = null;

            // Priority 1: external_games (category 1 = Steam) – UID is the AppID
            if (!empty($game['external_games']) && is_array($game['external_games'])) {
                foreach ($game['external_games'] as $ext) {
                    if (
                        is_array($ext) &&
                        ($ext['category'] ?? null) === 1 && // Steam
                        !empty($ext['uid']) &&
                        ctype_digit((string) $ext['uid'])
                    ) {
                        $appId = (int) $ext['uid'];
                        break;
                    }
                }
            }

            // Priority 2: websites – look for ANY Steam store URL containing /app/{number}
            if (!$appId && !empty($game['websites']) && is_array($game['websites'])) {
                foreach ($game['websites'] as $site) {
                    if (is_array($site) && !empty($site['url'])) {
                        $url = $site['url'];

                        // Improved regex: captures digits after /app/, handles paths and query strings
                        if (preg_match('#/app/(\d+)(?:/|$)#i', $url, $matches)) {
                            $appId = (int) $matches[1];
                            break;
                        }
                    }
                }
            }

            if ($appId) {
                $gameAppIdMap[$index] = $appId;
                \Log::debug("Found Steam AppID {$appId} for game index {$index}: " . ($game['name'] ?? 'Unknown'));
            } else {
                \Log::debug("No Steam AppID found for game index {$index}: " . ($game['name'] ?? 'Unknown'));
            }
        }

        if (empty($gameAppIdMap)) {
            return $igdbGames;
        }

        // Batch fetch from Steam (same as before, with error handling)
        $uniqueAppIds = array_unique(array_values($gameAppIdMap));

        $steamDetails = [];

        foreach ($uniqueAppIds as $appId) {
            try {
                $response = Http::timeout(15)
                    ->retry(3, 1000)
                    ->get('https://store.steampowered.com/api/appdetails', [
                        'appids'  => $appId, // Single AppID
                        'filters' => 'name,release_date,header_image,capsule_image,price_overview,platforms,metacritic,recommendations,is_free',
                        'cc'      => 'us',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // Response format: { "APPID": { "success": true, "data": { ... } } }
                    $info = $data[(string) $appId] ?? null;

                    if ($info['success'] ?? false) {
                        $steamDetails[$appId] = $info['data'];
                    }

                    // New: Fetch wishlist/followers from SteamDB
                    $wishlistCount = null;
                    try {
                        $steamdbResponse = Http::timeout(10)
                            ->get("https://steamdb.info/app/{$appId}/");

                        if ($steamdbResponse->successful()) {
                            // SteamDB shows followers in a table row like: <td>Followers</td><td>1,234,567</td>
                            preg_match('/Followers<\/td>\s*<td[^>]*>([\d,]+)</i', $steamdbResponse->body(), $matches);
                            if (isset($matches[1])) {
                                $wishlistCount = (int) str_replace(',', '', $matches[1]);
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning("SteamDB wishlist fetch failed for AppID {$appId}", ['error' => $e->getMessage()]);
                    }

                    // Add to steam_data
                    $igdbGames[$index]['steam']['wishlist_count'] = $wishlistCount;
                    $igdbGames[$index]['steam']['wishlist_formatted'] = $wishlistCount ? number_format($wishlistCount) : null;
                    $igdbGames[$index]['steam']['reviews_summary'] = [
                        'rating' => $steam['review_score_desc'] ?? null, // e.g., "Very Positive"
                        'percentage' => $steam['review_percentage'] ?? null,
                        'total' => $steam['total_reviews'] ?? null,
                    ];

                } else {
                    \Log::warning('Steam appdetails failed for single AppID', [
                        'appid'  => $appId,
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Steam API exception for AppID', [
                    'appid'    => $appId,
                    'message'  => $e->getMessage(),
                ]);
            }

            // Optional: gentle delay to stay well under rate limits
            usleep(300000); // 0.3 seconds
        }

        // Attach data
        foreach ($gameAppIdMap as $index => $appId) {
            if (isset($steamDetails[$appId])) {
                $steam = $steamDetails[$appId];

                $igdbGames[$index]['steam'] = [
                    'appid'             => $appId,
                    'header_image'      => $steam['header_image'] ?? null,
                    'capsule_image'     => $steam['capsule_image'] ?? $steam['header_image'] ?? null,
                    'release_date'      => $steam['release_date']['date'] ?? null,
                    'is_coming_soon'    => $steam['release_date']['coming_soon'] ?? true,
                    'price_overview'    => $steam['price_overview'] ?? null,
                    'is_free'           => $steam['is_free'] ?? false,
                    'platforms'         => $steam['platforms'] ?? null,
                    'metacritic_score'  => $steam['metacritic']['score'] ?? null,
                    'recommendations'   => $steam['recommendations']['total'] ?? null,
                ];
            }
        }

        return $igdbGames;
    }

    /**
     * Helper: Generate cover URL
     */
    public function getCoverUrl(?string $imageId, string $size = 'cover_big'): string
    {
        if (!$imageId) {
            return 'https://via.placeholder.com/300x400?text=No+Cover';
        }

        // Available sizes: thumb, cover_small, cover_big, logo_med, screenshot_med, etc.
        return "https://images.igdb.com/igdb/image/upload/t_{$size}/{$imageId}.jpg";
    }

    public function getScreenshotUrl(?string $imageId, string $size = 'screenshot_big'): string
    {
        if (!$imageId) return 'https://via.placeholder.com/1280x720?text=No+Screenshot';
        return "https://images.igdb.com/igdb/image/upload/t_{$size}/{$imageId}.jpg";
    }

    public function getYouTubeEmbedUrl(?string $videoId): string
    {
        if (!$videoId) return '';
        return "https://www.youtube.com/embed/{$videoId}?rel=0&autoplay=1";
    }

    public function getYouTubeThumbnailUrl(?string $videoId): string
    {
        if (!$videoId) return 'https://via.placeholder.com/1280x720?text=No+Trailer';
        return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
    }

    /**
     * Fetch game image from SteamGridDB when IGDB doesn't provide one
     *
     * @param string $gameName The game name to search for
     * @param string $type Image type: 'cover', 'hero', or 'logo'
     * @param int|null $steamAppId Optional Steam AppID for better matching
     * @param int|null $igdbId Optional IGDB ID for filename
     * @return string|null Filename of downloaded image, or null if failed
     */
    public function fetchImageFromSteamGridDb(string $gameName, string $type = 'cover', ?int $steamAppId = null, ?int $igdbId = null): ?string
    {
        $apiKey = config('services.steamgriddb.api_key');
        if (!$apiKey) {
            \Log::warning('SteamGridDB API key not configured');
            return null;
        }

        try {
            // Search for game
            $searchResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])
                ->timeout(10)
                ->get('https://www.steamgriddb.com/api/v2/search/autocomplete/' . urlencode($gameName));

            if ($searchResponse->failed() || empty($searchResponse->json()['data'])) {
                \Log::debug("SteamGridDB search failed for: {$gameName}");
                return null;
            }

            $searchResults = $searchResponse->json()['data'] ?? [];
            if (empty($searchResults)) {
                \Log::debug("No SteamGridDB results for: {$gameName}");
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
            if (!$gameId && !empty($searchResults[0]['id'])) {
                $gameId = $searchResults[0]['id'];
            }

            if (!$gameId) {
                \Log::debug("No valid game ID found in SteamGridDB results for: {$gameName}");
                return null;
            }

            // Fetch grids (covers)
            $gridsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])
                ->timeout(10)
                ->get("https://www.steamgriddb.com/api/v2/grids/game/{$gameId}");

            if ($gridsResponse->failed() || empty($gridsResponse->json()['data'])) {
                \Log::debug("SteamGridDB grids fetch failed for game ID: {$gameId}");
                return null;
            }

            $grids = $gridsResponse->json()['data'] ?? [];
            if (empty($grids)) {
                \Log::debug("No grids found for SteamGridDB game ID: {$gameId}");
                return null;
            }

            // Select grid based on requested type
            $selectedGrid = match ($type) {
                'hero' => $this->selectHeroGrid($grids),
                'logo' => $this->selectLogoGrid($grids),
                'cover' => $this->selectCoverGrid($grids),
                default => $grids[0] ?? null,
            };

            if (!$selectedGrid && !empty($grids)) {
                $selectedGrid = $grids[0];
            }

            $imageUrl = $selectedGrid['url'] ?? null;
            if (!$imageUrl) {
                \Log::debug("No image URL in selected grid for SteamGridDB game ID: {$gameId}");
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
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            // Generate filename
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = ($igdbId ?: ($steamAppId ?: 'game')) . '_' . time() . '.' . $extension;
            $filePath = $storagePath . '/' . $filename;

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
     * @param string $gameName The game name to search for
     * @param int|null $steamAppId Optional Steam AppID for better matching
     * @param int|null $igdbId Optional IGDB ID for filename
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
