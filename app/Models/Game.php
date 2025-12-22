<?php

namespace App\Models;

use App\Enums\GameTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    // === HELPER METHODS ===

    public function getCoverUrl(string $size = 'cover_big'): string
    {
        if (!$this->cover_image_id) {
            return 'https://via.placeholder.com/300x400?text=No+Cover';
        }
        return "https://images.igdb.com/igdb/image/upload/t_{$size}/{$this->cover_image_id}.jpg";
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
        return GameTypeEnum::fromValue($this->game_type ?? 0) ?? GameTypeEnum::MAIN;
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
            $query = "fields name, first_release_date, summary, platforms.name, cover.image_id,
                         genres.name, genres.id, game_modes.name, game_modes.id,
                         screenshots.image_id, videos.video_id,
                         external_games.category, external_games.uid,
                         websites.category, websites.url, game_type;
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

            // Create game in database
            $game = self::create([
                'igdb_id' => $igdbGame['id'],
                'name' => $igdbGame['name'] ?? 'Unknown Game',
                'summary' => $igdbGame['summary'] ?? null,
                'first_release_date' => isset($igdbGame['first_release_date'])
                    ? \Carbon\Carbon::createFromTimestamp($igdbGame['first_release_date'])
                    : null,
                'cover_image_id' => $igdbGame['cover']['image_id'] ?? null,
                'game_type' => $igdbGame['game_type'] ?? 0,
                'steam_data' => $igdbGame['steam'] ?? null,
                'screenshots' => $igdbGame['screenshots'] ?? null,
                'trailers' => $igdbGame['videos'] ?? null,
                'similar_games' => $igdbGame['similar_games'] ?? null,
            ]);

            // Sync relations (platforms, genres, game modes)
            self::syncRelations($game, $igdbGame);

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
    }
}
