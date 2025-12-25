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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CreateGameList extends Command
{
    protected $signature = 'games:lists:create
                            {--name= : List name}
                            {--start-date= : Start date in Y-m-d format}
                            {--end-date= : End date in Y-m-d format}
                            {--is-active= : Set list as active (yes/no)}
                            {--is-public= : Set list as public (yes/no)}
                            {--is-system= : Set list as system list (yes/no)}
                            {--igdb-ids= : Comma-separated IGDB game IDs}';

    protected $description = 'Create a game list with games from IGDB. Fetches missing games from IGDB if needed.';

    public function handle(IgdbService $igdbService): int
    {
        // Get or ask for required options with validation
        $name = $this->option('name');
        while (!$name) {
            $name = $this->ask('List name');
            if (!$name) {
                $this->error('List name is required.');
            }
        }

        $startDate = $this->option('start-date');
        $startDateObj = null;
        while (!$startDateObj) {
            if (!$startDate) {
                $startDate = $this->ask('Start date (Y-m-d format, e.g., 2026-01-01)');
            }
            
            if ($startDate) {
                try {
                    $startDateObj = Carbon::createFromFormat('Y-m-d', $startDate);
                } catch (\Exception $e) {
                    $this->error('Invalid date format. Use Y-m-d format (e.g., 2026-01-01).');
                    $startDate = null;
                }
            }
        }

        $endDate = $this->option('end-date');
        $endDateObj = null;
        while (!$endDateObj) {
            if (!$endDate) {
                $endDate = $this->ask('End date (Y-m-d format, e.g., 2026-01-31)');
            }
            
            if ($endDate) {
                try {
                    $endDateObj = Carbon::createFromFormat('Y-m-d', $endDate);
                    
                    // Validate date range
                    if ($startDateObj->gt($endDateObj)) {
                        $this->error('End date must be after or equal to start date.');
                        $endDate = null;
                        continue;
                    }
                } catch (\Exception $e) {
                    $this->error('Invalid date format. Use Y-m-d format (e.g., 2026-01-31).');
                    $endDate = null;
                }
            }
        }

        $igdbIds = $this->option('igdb-ids');
        $igdbIdArray = [];
        while (empty($igdbIdArray)) {
            if (!$igdbIds) {
                $igdbIds = $this->ask('IGDB game IDs (comma-separated, e.g., 12345,67890,11111)');
            }
            
            if ($igdbIds) {
                $igdbIdArray = array_map('trim', explode(',', $igdbIds));
                $igdbIdArray = array_filter($igdbIdArray, fn($id) => !empty($id) && is_numeric($id));
                
                if (empty($igdbIdArray)) {
                    $this->error('Please provide at least one valid numeric IGDB ID.');
                    $igdbIds = null;
                }
            }
        }

        // Ensure dates are set to start/end of day
        $startDateObj = $startDateObj->startOfDay();
        $endDateObj = $endDateObj->endOfDay();

        // Get boolean options with prompts
        $isActive = $this->option('is-active') !== null
            ? $this->parseBooleanOption($this->option('is-active'))
            : $this->confirm('Is the list active?', true);

        $isPublic = $this->option('is-public') !== null
            ? $this->parseBooleanOption($this->option('is-public'))
            : $this->confirm('Is the list public?', true);

        $isSystem = $this->option('is-system') !== null
            ? $this->parseBooleanOption($this->option('is-system'))
            : $this->confirm('Is this a system list?', false);

        // Generate slug if system list
        $slug = null;
        if ($isSystem) {
            $slug = $this->generateUniqueSlug($name);
        }

        $this->info("Creating game list: {$name}");
        $this->info("Start date: {$startDateObj->format('Y-m-d')}");
        $this->info("End date: {$endDateObj->format('Y-m-d')}");
        $this->info("Processing " . count($igdbIdArray) . " game(s)...");

        // Create the game list
        $gameList = GameList::create([
            'user_id' => 1,
            'name' => $name,
            'description' => null,
            'slug' => $slug,
            'is_public' => $isPublic,
            'is_system' => $isSystem,
            'is_active' => $isActive,
            'start_at' => $startDateObj->startOfDay(),
            'end_at' => $endDateObj->endOfDay(),
        ]);

        $this->info("Game list created with ID: {$gameList->id}");

        // Process each IGDB ID
        $successCount = 0;
        $failCount = 0;
        $order = 1;

        foreach ($igdbIdArray as $igdbId) {
            $this->line("Processing IGDB ID: {$igdbId}...");

            try {
                $game = $this->getOrFetchGame((int)$igdbId, $igdbService);

                if (!$game) {
                    $this->warn("  Failed to fetch game with IGDB ID: {$igdbId}");
                    $failCount++;
                    continue;
                }

                // Check if game is already in list
                if ($gameList->games()->where('game_id', $game->id)->exists()) {
                    $this->warn("  Game '{$game->name}' is already in the list, skipping...");
                    continue;
                }

                // Attach game to list with order
                $gameList->games()->attach($game->id, ['order' => $order]);
                $this->info("  ✓ Added: {$game->name}");
                $successCount++;
                $order++;
            } catch (\Exception $e) {
                $this->error("  ✗ Error processing IGDB ID {$igdbId}: {$e->getMessage()}");
                $failCount++;
            }
        }

        // Summary
        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("List ID: {$gameList->id}");
        $this->info("List Name: {$gameList->name}");
        if ($gameList->slug) {
            $this->info("Slug: {$gameList->slug}");
        }
        $this->info("Games successfully added: {$successCount}");
        if ($failCount > 0) {
            $this->warn("Games failed: {$failCount}");
        }
        $this->info("Total games in list: {$gameList->games()->count()}");

        return Command::SUCCESS;
    }

    /**
     * Get game from database or fetch from IGDB if not exists.
     */
    private function getOrFetchGame(int $igdbId, IgdbService $igdbService): ?Game
    {
        // Check if game exists in database
        $game = Game::where('igdb_id', $igdbId)->first();

        if ($game) {
            $this->line("  Game found in database: {$game->name}");
            return $game;
        }

        // Fetch from IGDB
        $this->line("  Fetching game from IGDB...");

        try {
            $query = "fields name, first_release_date, summary, platforms.name, platforms.id, cover.image_id,
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
                $this->warn("  Game not found in IGDB.");
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
            
            // If IGDB didn't provide a cover, try SteamGridDB
            if (!$coverImageId) {
                $steamGridDbCover = $igdbService->fetchImageFromSteamGridDb($gameName, 'cover', $steamAppId, $igdbGameId);
                if ($steamGridDbCover) {
                    $coverImageId = $steamGridDbCover;
                }
            }

            // For hero: Use IGDB cover if available, else fetch from SteamGridDB
            $heroImageId = $igdbGame['cover']['image_id'] ?? null;
            if (!$heroImageId) {
                $steamGridDbHero = $igdbService->fetchImageFromSteamGridDb($gameName, 'hero', $steamAppId, $igdbGameId);
                if ($steamGridDbHero) {
                    $heroImageId = $steamGridDbHero;
                }
            }

            // For logo: Fetch from SteamGridDB
            $logoImageId = $igdbService->fetchImageFromSteamGridDb($gameName, 'logo', $steamAppId, $igdbGameId);

            // Create game in database
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

            $this->line("  Game created in database: {$game->name}");
            return $game;
        } catch (\Exception $e) {
            $this->error("  Error fetching from IGDB: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Sync game relations (platforms, genres, game modes).
     */
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

    /**
     * Generate a unique slug from name.
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Parse boolean option value.
     */
    private function parseBooleanOption(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $value = strtolower(trim($value));
        
        return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
