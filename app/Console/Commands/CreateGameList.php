<?php

namespace App\Console\Commands;

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
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
        while (! $name) {
            $name = $this->ask('List name');
            if (! $name) {
                $this->error('List name is required.');
            }
        }

        $startDate = $this->option('start-date');
        $startDateObj = null;
        while (! $startDateObj) {
            if (! $startDate) {
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
        while (! $endDateObj) {
            if (! $endDate) {
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
            if (! $igdbIds) {
                $igdbIds = $this->ask('IGDB game IDs (comma-separated, e.g., 12345,67890,11111)');
            }

            if ($igdbIds) {
                $igdbIdArray = array_map('trim', explode(',', $igdbIds));
                $igdbIdArray = array_filter($igdbIdArray, fn ($id) => ! empty($id) && is_numeric($id));

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
        $this->info('Processing '.count($igdbIdArray).' game(s)...');

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
                $game = $this->getOrFetchGame((int) $igdbId, $igdbService);

                if (! $game) {
                    $this->warn("  Failed to fetch game with IGDB ID: {$igdbId}");
                    $failCount++;

                    continue;
                }

                // Check if game is already in list
                if ($gameList->games()->where('game_id', $game->id)->exists()) {
                    $this->warn("  Game '{$game->name}' is already in the list, skipping...");

                    continue;
                }

                // Attach game to list with order, release_date, and platforms
                $game->load('platforms');
                $platformIds = $game->platforms
                    ->filter(fn ($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                    ->map(fn ($p) => $p->igdb_id)
                    ->values()
                    ->toArray();

                $gameList->games()->attach($game->id, [
                    'order' => $order,
                    'release_date' => $game->first_release_date,
                    'platforms' => json_encode($platformIds),
                ]);
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
        $this->info('=== Summary ===');
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
     *
     * Delegates to the canonical Game::fetchFromIgdbIfMissing() so a single
     * code path handles fetching, relation syncing and async image retrieval.
     */
    private function getOrFetchGame(int $igdbId, IgdbService $igdbService): ?Game
    {
        return Game::fetchFromIgdbIfMissing($igdbId, $igdbService);
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
            $slug = $originalSlug.'-'.$counter;
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
