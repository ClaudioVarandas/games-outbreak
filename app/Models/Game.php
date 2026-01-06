<?php

namespace App\Models;

use App\Enums\GameTypeEnum;
use App\Enums\PlatformEnum;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\File;

class Game extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'first_release_date' => 'datetime',
        'steam_data' => 'array',
        'screenshots' => 'array',
        'trailers' => 'array',
        'similar_games' => 'array',
        'game_type' => 'integer',
        'raw_igdb_json' => 'array',
        'last_igdb_sync_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'game_platform');
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'game_genre');
    }

    public function gameModes(): BelongsToMany
    {
        return $this->belongsToMany(GameMode::class, 'game_game_mode');
    }

    public function gameLists(): BelongsToMany
    {
        return $this->belongsToMany(GameList::class, 'game_list_game')
            ->withPivot('order')
            ->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_game')
            ->withPivot('is_developer', 'is_publisher')
            ->withTimestamps();
    }

    public function gameEngines(): BelongsToMany
    {
        return $this->belongsToMany(GameEngine::class, 'game_engine_game')
            ->withTimestamps();
    }

    public function playerPerspectives(): BelongsToMany
    {
        return $this->belongsToMany(PlayerPerspective::class, 'game_player_perspective')
            ->withTimestamps();
    }

    public function releaseDates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GameReleaseDate::class);
    }

    // === HELPER METHODS ===

    /**
     * Check if image_id is an IGDB ID (alphanumeric) or a local filename
     * IGDB IDs are alphanumeric strings (e.g., "coauth", "co9xkp")
     * Local filenames from SteamGridDB have extensions (e.g., "12345_1234567890.jpg")
     */
    private function isIgdbCoverId(?string $imageId): bool
    {
        if (!$imageId) {
            return false;
        }
        // Local filenames from SteamGridDB have file extensions (e.g., ".jpg", ".png")
        // IGDB IDs are alphanumeric strings without extensions
        return !preg_match('/\.(jpg|jpeg|png|webp)$/i', $imageId);
    }

    /**
     * Get image URL from image ID (works for both IGDB IDs and local filenames)
     */
    private function getImageUrl(?string $imageId, string $size = 'cover_big'): string
    {
        if (!$imageId) {
            return $this->getPlaceholderUrl();
        }

        // If IGDB ID (no extension), use IGDB URL
        if ($this->isIgdbCoverId($imageId)) {
            return "https://images.igdb.com/igdb/image/upload/t_{$size}/{$imageId}.jpg";
        }

        // Otherwise, local file from SteamGridDB
        $filePath = storage_path('app/public/covers/' . $imageId);
        if (File::exists($filePath)) {
            return asset('storage/covers/' . $imageId);
        }

        return $this->getPlaceholderUrl();
    }

    public function getPlaceholderUrl(): string
    {
        // Return a data URL that will be handled by the placeholder component
        // This is a fallback for cases where we can't use the Blade component
        return asset('images/game-cover-placeholder.svg');
    }

    public function getCoverUrl(string $size = 'cover_big'): string
    {
        // If cover_image_id exists, use it
        if ($this->cover_image_id) {
            return $this->getImageUrl($this->cover_image_id, $size);
        }

        // Fallback to hero_image_id if available
        if ($this->hero_image_id) {
            return $this->getImageUrl($this->hero_image_id, $size);
        }

        return $this->getPlaceholderUrl();
    }

    public function getHeroImageUrl(): string
    {
        // If hero_image_id exists, use it at 720p
        if ($this->hero_image_id) {
            return $this->getImageUrl($this->hero_image_id, '720p');
        }

        // Fallback to cover_image_id at 720p
        if ($this->cover_image_id) {
            return $this->getImageUrl($this->cover_image_id, '720p');
        }

        // Final fallback to placeholder
        return $this->getPlaceholderUrl();
    }

    public function getLogoImageUrl(): string
    {
        // If logo_image_id exists, use it
        if ($this->logo_image_id) {
            return $this->getImageUrl($this->logo_image_id, 'logo_med');
        }

        return $this->getPlaceholderUrl();
    }

    public function getScreenshotUrl(?string $imageId, string $size = 'screenshot_big'): string
    {
        if (!$imageId) {
            return 'https://via.placeholder.com/1280x720?text=No+Screenshot';
        }
        return "https://images.igdb.com/igdb/image/upload/t_{$size}/{$imageId}.jpg";
    }

    public function getPrimaryScreenshot(string $size = 'screenshot_big'): string
    {
        $first = collect($this->screenshots)->first();
        return $this->getScreenshotUrl($first['image_id'] ?? null, $size);
    }

    public function getYouTubeEmbedUrl(?string $videoId): string
    {
        if (!$videoId) return '';
        return "https://www.youtube.com/embed/{$videoId}?rel=0&modestbranding=1&autoplay=1";
    }

    public function getYouTubeThumbnailUrl(?string $videoId): string
    {
        if (!$videoId) return 'https://via.placeholder.com/1280x720?text=No+Trailer';
        return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
    }

    public function getPrimaryTrailerEmbed(): string
    {
        $first = collect($this->trailers)->first();
        if (!$first || empty($first['video_id'])) {
            return '';
        }

        $videoId = $first['video_id'];

        // Critical parameters for autoplay + clean look
        $params = http_build_query([
            'autoplay' => 1,              // Auto-play (muted by default on most browsers)
            'mute' => 1,                  // Required for autoplay in Chrome/Firefox
            'rel' => 0,                   // No related videos at end
            'modestbranding' => 1,        // Smaller YouTube logo
            'controls' => 1,              // Show controls (play, volume, etc.)
            'showinfo' => 0,              // Deprecated, but harmless
            'iv_load_policy' => 3,        // Hide annotations
            'fs' => 1,                    // Allow fullscreen
            'playsinline' => 1,           // Play inline on mobile
            'enablejsapi' => 1,           // Optional: for JS control later
        ]);

        return "https://www.youtube.com/embed/{$videoId}?{$params}";
    }

    public function getPrimaryTrailerThumbnail(): string
    {
        $first = collect($this->trailers)->first();
        return $this->getYouTubeThumbnailUrl($first['video_id'] ?? null);
    }

    public function getSteamPrice(): ?string
    {
        return $this->steam_data['price_overview']['final_formatted'] ?? null;
    }

    public function getGameTypeEnum(): GameTypeEnum
    {
        $gameType = $this->game_type ?? 0;
        $gameName = $this->name ?? '';

        // Detect bundles by name (IGDB sometimes classifies bundles as PORT/3 instead of BUNDLE/5)
        $isBundle = stripos($gameName, 'Bundle') !== false || stripos($gameName, 'Collection') !== false;

        // If it's a bundle by name, treat it as bundle regardless of game_type
        if ($isBundle && $gameType !== 5) {
            $gameType = 5; // Force to BUNDLE
        }

        return GameTypeEnum::fromValue($gameType) ?? GameTypeEnum::MAIN;
    }

    public function getDevelopers()
    {
        return $this->companies()->wherePivot('is_developer', true)->get();
    }

    public function getPublishers()
    {
        return $this->companies()->wherePivot('is_publisher', true)->get();
    }

    /**
     * Sync release dates from IGDB data to game_release_dates table
     */
    public static function syncReleaseDates(Game $game, ?array $igdbReleaseDates): void
    {
        if (empty($igdbReleaseDates)) {
            // Remove all existing release dates if IGDB has none
            $game->releaseDates()->where('is_manual', false)->delete();
            return;
        }

        // Get all existing IGDB-synced release dates
        $existingReleaseDates = $game->releaseDates()
            ->where('is_manual', false)
            ->get();

        // Track which records we've processed to avoid orphans
        $processedRecordIds = [];

        foreach ($igdbReleaseDates as $igdbReleaseDate) {
            $igdbId = $igdbReleaseDate['id'] ?? null;

            // Find platform_id
            $platformId = null;
            if (isset($igdbReleaseDate['platform'])) {
                $platform = Platform::where('igdb_id', $igdbReleaseDate['platform'])->first();
                $platformId = $platform?->id;

                // Skip if platform doesn't exist - critical data missing
                if (!$platformId) {
                    \Log::warning("Skipping release date - platform not found", [
                        'game_id' => $game->id,
                        'igdb_platform_id' => $igdbReleaseDate['platform'],
                        'igdb_release_date_id' => $igdbReleaseDate['id'] ?? null,
                    ]);
                    continue;
                }
            }

            // Find status_id
            $statusId = null;
            if (isset($igdbReleaseDate['status'])) {
                $status = ReleaseDateStatus::where('igdb_id', $igdbReleaseDate['status'])->first();
                $statusId = $status?->id;
            }

            // Parse date
            $date = null;
            if (isset($igdbReleaseDate['date'])) {
                try {
                    $date = Carbon::createFromTimestamp($igdbReleaseDate['date']);
                } catch (\Exception $e) {
                    continue; // Skip invalid dates
                }
            }

            $data = [
                'game_id' => $game->id,
                'platform_id' => $platformId,
                'status_id' => $statusId,
                'igdb_release_date_id' => $igdbId,
                'date' => $date,
                'year' => $igdbReleaseDate['y'] ?? null,
                'month' => $igdbReleaseDate['m'] ?? null,
                'day' => $igdbReleaseDate['d'] ?? null,
                'region' => $igdbReleaseDate['region'] ?? null,
                'human_readable' => $igdbReleaseDate['human'] ?? null,
                'is_manual' => false,
            ];

            // Find existing record
            $existing = self::findExistingReleaseDate(
                $existingReleaseDates,
                $igdbId,
                $game->id,
                $platformId,
                $date,
                $igdbReleaseDate['region'] ?? null
            );

            if ($existing) {
                // Update existing record
                $existing->update($data);
                $processedRecordIds[] = $existing->id;
            } else {
                // Create new record
                $created = GameReleaseDate::create($data);
                $processedRecordIds[] = $created->id;
            }
        }

        // Delete IGDB release dates that no longer exist in IGDB data
        $game->releaseDates()
            ->where('is_manual', false)
            ->whereNotIn('id', $processedRecordIds)
            ->delete();
    }

    /**
     * Find existing release date record
     * Tries matching by igdb_release_date_id first, falls back to composite key
     */
    private static function findExistingReleaseDate(
        $existingReleaseDates,
        ?int $igdbId,
        int $gameId,
        ?int $platformId,
        $date,
        ?int $region
    ): ?GameReleaseDate {
        // Try matching by IGDB ID first (most reliable)
        if ($igdbId) {
            $match = $existingReleaseDates->firstWhere('igdb_release_date_id', $igdbId);
            if ($match) {
                return $match;
            }
        }

        // Fallback: Match by composite key (game_id, platform_id, date, region)
        // This handles cases where IGDB doesn't provide an ID
        return $existingReleaseDates->first(function ($releaseDate) use ($gameId, $platformId, $date, $region) {
            // Must match game, platform, and date
            if ($releaseDate->game_id !== $gameId) {
                return false;
            }

            if ($releaseDate->platform_id !== $platformId) {
                return false;
            }

            // Compare dates (handle null dates)
            $existingDate = $releaseDate->date?->format('Y-m-d');
            $newDate = $date ? $date->format('Y-m-d') : null;
            if ($existingDate !== $newDate) {
                return false;
            }

            // Region can be null, so handle that
            if ($releaseDate->region !== $region) {
                return false;
            }

            return true;
        });
    }

    /**
     * Fetch a game from IGDB if it doesn't exist in the database
     */
    public static function fetchFromIgdbIfMissing(int $igdbId, \App\Services\IgdbService $igdbService): ?self
    {
        // Check if game already exists
        $game = self::with('platforms')->where('igdb_id', $igdbId)->first();
        if ($game) {
            return $game;
        }

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

            $response = \Illuminate\Support\Facades\Http::igdb()
                ->withBody($query, 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            if ($response->failed() || empty($response->json())) {
                \Log::warning("Failed to fetch game from IGDB", ['igdb_id' => $igdbId]);
                return null;
            }

            $igdbGame = $response->json()[0];

            // Enrich with Steam data
            $igdbGame = $igdbService->enrichWithSteamData([$igdbGame])[0] ?? $igdbGame;

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

            // Create game in database
            $game = self::create([
                'igdb_id' => $igdbGame['id'],
                'name' => $gameName,
                'summary' => $igdbGame['summary'] ?? null,
                'first_release_date' => isset($igdbGame['first_release_date'])
                    ? \Carbon\Carbon::createFromTimestamp($igdbGame['first_release_date'])
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

            // Sync relations (platforms, genres, game modes, release dates)
            self::syncRelations($game, $igdbGame);
            self::syncReleaseDates($game, $igdbGame['release_dates'] ?? null);

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

            // Reload with relationships
            $game->load('platforms');

            return $game;
        } catch (\Exception $e) {
            \Log::error("Error fetching game from IGDB", [
                'igdb_id' => $igdbId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Sync game relations (platforms, genres, game modes)
     */
    private static function syncRelations(self $game, array $igdbGame): void
    {
        if (!empty($igdbGame['platforms'])) {
            $ids = collect($igdbGame['platforms'])->map(fn($p) =>
                \App\Models\Platform::firstOrCreate(['igdb_id' => $p['id']], ['name' => $p['name'] ?? 'Unknown'])->id
            );
            $game->platforms()->sync($ids);
        }

        if (!empty($igdbGame['genres'])) {
            $ids = collect($igdbGame['genres'])->map(fn($g) =>
                \App\Models\Genre::firstOrCreate(['igdb_id' => $g['id']], ['name' => $g['name'] ?? 'Unknown'])->id
            );
            $game->genres()->sync($ids);
        }

        if (!empty($igdbGame['game_modes'])) {
            $ids = collect($igdbGame['game_modes'])->map(fn($m) =>
                \App\Models\GameMode::firstOrCreate(['igdb_id' => $m['id']], ['name' => $m['name'] ?? 'Unknown'])->id
            );
            $game->gameModes()->sync($ids);
        }

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

        if (!empty($igdbGame['game_engines'])) {
            $ids = collect($igdbGame['game_engines'])->map(fn($e) =>
                \App\Models\GameEngine::firstOrCreate(['igdb_id' => $e['id']], ['name' => $e['name'] ?? 'Unknown'])->id
            );
            $game->gameEngines()->sync($ids);
        }

        if (!empty($igdbGame['player_perspectives'])) {
            $ids = collect($igdbGame['player_perspectives'])->map(fn($p) =>
                \App\Models\PlayerPerspective::firstOrCreate(['igdb_id' => $p['id']], ['name' => $p['name'] ?? 'Unknown'])->id
            );
            $game->playerPerspectives()->sync($ids);
        }
    }

    // === UPDATE TRACKING & PRIORITY METHODS ===

    /**
     * Mark game as viewed by user
     */
    public function markAsViewed(): void
    {
        $this->increment('view_count');
        $this->update(['last_viewed_at' => now()]);
        $this->calculateAndSaveUpdatePriority();
    }

    /**
     * Calculate update priority score (0-100)
     * Higher score = higher priority for updates
     */
    public function calculateUpdatePriority(): int
    {
        $score = 0;

        // 1. View count (0-40 points) - logarithmic scale
        if ($this->view_count > 0) {
            $score += min(log($this->view_count + 1, 2) * 5, 40);
        }

        // 2. Days since release (0-30 points) - newer games = higher priority
        if ($this->first_release_date) {
            $daysSinceRelease = abs($this->first_release_date->diffInDays(now()));

            if ($daysSinceRelease <= 30) {
                $score += 30; // Recently released
            } elseif ($daysSinceRelease <= 90) {
                $score += 20; // Released in last 3 months
            } elseif ($daysSinceRelease <= 180) {
                $score += 10; // Released in last 6 months
            }
        }

        // 3. Days since last sync (0-20 points) - older sync = higher priority
        if ($this->last_igdb_sync_at) {
            $daysSinceSync = $this->last_igdb_sync_at->diffInDays(now());

            if ($daysSinceSync > 90) {
                $score += 20; // Very stale
            } elseif ($daysSinceSync > 60) {
                $score += 15;
            } elseif ($daysSinceSync > 30) {
                $score += 10;
            } elseif ($daysSinceSync > 14) {
                $score += 5;
            }
        } else {
            // Never synced = high priority
            $score += 20;
        }

        // 4. Missing data (0-10 points)
        if (!$this->cover_image_id || !$this->summary || !$this->steam_data) {
            $score += 10;
        }

        return min((int) round($score), 100);
    }

    /**
     * Calculate and save update priority
     */
    public function calculateAndSaveUpdatePriority(): void
    {
        $this->update(['update_priority' => $this->calculateUpdatePriority()]);
    }

    /**
     * Determine if game should be updated
     */
    public function shouldUpdate(int $minDaysSinceSync = 30): bool
    {
        // Never synced
        if (!$this->last_igdb_sync_at) {
            return true;
        }

        // Check if stale
        if ($this->last_igdb_sync_at->diffInDays(now()) >= $minDaysSinceSync) {
            return true;
        }

        // High priority games get more frequent updates
        if ($this->update_priority >= 80 && $this->last_igdb_sync_at->diffInDays(now()) >= 7) {
            return true;
        }

        // Missing critical data
        if (!$this->cover_image_id || !$this->summary) {
            return true;
        }

        return false;
    }

    /**
     * Refresh game data from IGDB with change detection
     */
    public function refreshFromIgdb(IgdbService $igdbService): bool
    {
        try {
            // Fetch fresh data from IGDB
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
                         where id = {$this->igdb_id}; limit 1;";

            $response = \Illuminate\Support\Facades\Http::igdb()
                ->withBody($query, 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            if ($response->failed() || empty($response->json())) {
                \Log::warning("Failed to refresh game data from IGDB", ['igdb_id' => $this->igdb_id]);
                return false;
            }

            $igdbGame = $response->json()[0];

            // Enrich with Steam data
            $igdbGame = $igdbService->enrichWithSteamData([$igdbGame])[0] ?? $igdbGame;

            // Detect changes using hash comparison
            $currentHash = $this->generateDataHash();

            // Prepare update data
            $updateData = [
                'name' => $igdbGame['name'] ?? $this->name,
                'summary' => $igdbGame['summary'] ?? $this->summary,
                'first_release_date' => isset($igdbGame['first_release_date'])
                    ? Carbon::createFromTimestamp($igdbGame['first_release_date'])
                    : $this->first_release_date,
                'cover_image_id' => $igdbGame['cover']['image_id'] ?? $this->cover_image_id,
                'game_type' => $igdbGame['game_type'] ?? $this->game_type,
                'steam_data' => $igdbGame['steam'] ?? $this->steam_data,
                'steam_wishlist_count' => $igdbGame['steam']['wishlist_count'] ?? $this->steam_wishlist_count,
                'similar_games' => $igdbGame['similar_games'] ?? $this->similar_games,
                'screenshots' => $igdbGame['screenshots'] ?? $this->screenshots,
                'trailers' => $igdbGame['videos'] ?? $this->trailers,
                'last_igdb_sync_at' => now(),
            ];

            // Create temporary model with new data to compare hashes
            $tempAttributes = array_merge($this->attributes, $updateData);
            $newHash = $this->generateDataHash($tempAttributes);

            // Only update if data actually changed
            if ($currentHash !== $newHash) {
                $this->update($updateData);

                // Sync relations and release dates
                self::syncRelations($this, $igdbGame);
                self::syncReleaseDates($this, $igdbGame['release_dates'] ?? null);

                \Log::info("Updated game data from IGDB", [
                    'igdb_id' => $this->igdb_id,
                    'name' => $this->name,
                    'changes_detected' => true
                ]);

                return true;
            }

            // Even if no changes, update sync timestamp
            $this->update(['last_igdb_sync_at' => now()]);

            \Log::info("No changes detected for game", [
                'igdb_id' => $this->igdb_id,
                'name' => $this->name
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error("Error refreshing game from IGDB", [
                'igdb_id' => $this->igdb_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate hash of critical game data for change detection
     */
    private function generateDataHash(?array $attributes = null): string
    {
        $data = $attributes ?? $this->attributes;

        $criticalFields = [
            'name' => $data['name'] ?? '',
            'summary' => $data['summary'] ?? '',
            'first_release_date' => $data['first_release_date'] ?? '',
            'cover_image_id' => $data['cover_image_id'] ?? '',
            'game_type' => $data['game_type'] ?? 0,
            'steam_data' => is_string($data['steam_data'] ?? null)
                ? $data['steam_data']
                : json_encode($data['steam_data'] ?? []),
            'screenshots' => is_string($data['screenshots'] ?? null)
                ? $data['screenshots']
                : json_encode($data['screenshots'] ?? []),
            'trailers' => is_string($data['trailers'] ?? null)
                ? $data['trailers']
                : json_encode($data['trailers'] ?? []),
        ];

        return md5(json_encode($criticalFields));
    }
}
