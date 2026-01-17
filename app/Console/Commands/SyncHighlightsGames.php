<?php

namespace App\Console\Commands;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformGroupEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncHighlightsGames extends Command
{
    protected $signature = 'highlights:sync {--year= : Year to sync highlights for (defaults to current year)} {--dry-run : Preview changes without applying them} {--fix-platform-groups : Re-evaluate and fix platform groups for existing games}';

    protected $description = 'Sync games marked as highlights from monthly/indie lists to the yearly highlights list.';

    public function handle(): int
    {
        $year = $this->option('year') ?? date('Y');
        $year = (int) $year;
        $dryRun = $this->option('dry-run');

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Syncing highlights for year: {$year}");

        // Find the yearly highlights list
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        $highlightsList = GameList::where('list_type', ListTypeEnum::HIGHLIGHTS->value)
            ->where('is_system', true)
            ->whereBetween('start_at', [$startOfYear, $endOfYear])
            ->first();

        if (! $highlightsList) {
            $this->error("No highlights list found for {$year}. Create one first with: php artisan highlights:create-yearly --year={$year}");

            return Command::FAILURE;
        }

        $this->info("Found highlights list: '{$highlightsList->name}' (ID: {$highlightsList->id})");

        // Fix platform groups for existing games if requested
        if ($this->option('fix-platform-groups')) {
            $this->fixPlatformGroups($highlightsList, $dryRun);

            return Command::SUCCESS;
        }

        // Find all monthly and indie lists for this year
        $sourceLists = GameList::where('is_system', true)
            ->whereIn('list_type', [ListTypeEnum::MONTHLY->value, ListTypeEnum::INDIE_GAMES->value])
            ->where(function ($query) use ($startOfYear, $endOfYear) {
                $query->whereBetween('start_at', [$startOfYear, $endOfYear])
                    ->orWhereBetween('end_at', [$startOfYear, $endOfYear]);
            })
            ->with(['games' => function ($query) {
                $query->wherePivot('is_highlight', true);
            }])
            ->get();

        $this->info("Found {$sourceLists->count()} source lists (monthly/indie)");

        $addedCount = 0;
        $skippedCount = 0;

        foreach ($sourceLists as $sourceList) {
            $highlightedGames = $sourceList->games;

            if ($highlightedGames->isEmpty()) {
                continue;
            }

            $this->line("  Processing: {$sourceList->name} ({$highlightedGames->count()} highlighted games)");

            foreach ($highlightedGames as $game) {
                // Check directly in DB if game already exists in highlights list
                if ($highlightsList->games()->where('games.id', $game->id)->exists()) {
                    $this->line("    - Skipped: {$game->name} (already in highlights)");
                    $skippedCount++;

                    continue;
                }

                // Get platforms from the source pivot, fallback to game's platforms
                $platforms = $game->pivot->platforms;
                if (is_string($platforms)) {
                    $platforms = json_decode($platforms, true) ?? [];
                }
                if (! is_array($platforms) || empty($platforms)) {
                    $game->load('platforms');
                    $platforms = $game->platforms
                        ->filter(fn ($p) => \App\Enums\PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                        ->map(fn ($p) => $p->igdb_id)
                        ->values()
                        ->toArray();
                }

                // Determine platform group
                $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platforms);

                if ($dryRun) {
                    $this->line("    + Would add: {$game->name} ({$platformGroup->label()})");
                } else {
                    $maxOrder = $highlightsList->games()->max('order') ?? 0;
                    $highlightsList->games()->attach($game->id, [
                        'order' => $maxOrder + 1,
                        'release_date' => $game->pivot->release_date,
                        'platforms' => json_encode($platforms),
                        'platform_group' => $platformGroup->value,
                        'is_highlight' => false,
                    ]);
                    $this->line("    + Added: {$game->name} ({$platformGroup->label()})");
                }

                $addedCount++;
            }
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->info(($dryRun ? 'Would add: ' : 'Added: ')."{$addedCount} game(s)");
        $this->info("Skipped: {$skippedCount} game(s) (already in highlights)");

        return Command::SUCCESS;
    }

    /**
     * Fix platform groups for all existing games in the highlights list.
     */
    protected function fixPlatformGroups(GameList $highlightsList, bool $dryRun): void
    {
        $this->info(($dryRun ? '[DRY RUN] ' : '').'Fixing platform groups for existing games...');

        $games = $highlightsList->games()->with('platforms')->get();
        $fixedCount = 0;

        foreach ($games as $game) {
            $currentGroup = $game->pivot->platform_group;

            // Get platforms from pivot, fallback to game's platforms
            $platforms = $game->pivot->platforms;
            if (is_string($platforms)) {
                $platforms = json_decode($platforms, true) ?? [];
            }
            if (! is_array($platforms) || empty($platforms)) {
                $platforms = $game->platforms
                    ->filter(fn ($p) => \App\Enums\PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                    ->map(fn ($p) => $p->igdb_id)
                    ->values()
                    ->toArray();
            }

            $suggestedGroup = PlatformGroupEnum::suggestFromPlatforms($platforms);

            if ($currentGroup !== $suggestedGroup->value) {
                if ($dryRun) {
                    $this->line("  Would fix: {$game->name} ({$currentGroup} -> {$suggestedGroup->value})");
                } else {
                    $highlightsList->games()->updateExistingPivot($game->id, [
                        'platforms' => json_encode($platforms),
                        'platform_group' => $suggestedGroup->value,
                    ]);
                    $this->line("  Fixed: {$game->name} ({$currentGroup} -> {$suggestedGroup->value})");
                }
                $fixedCount++;
            }
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->info(($dryRun ? 'Would fix: ' : 'Fixed: ')."{$fixedCount} game(s)");
        $this->info('Unchanged: '.($games->count() - $fixedCount).' game(s)');
    }
}
