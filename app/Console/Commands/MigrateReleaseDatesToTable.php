<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateReleaseDatesToTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:migrate-release-dates
                            {--chunk=100 : Number of games to process per chunk}
                            {--dry-run : Run without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate release_dates from JSON field to game_release_dates table (production-safe with chunking)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Count total games with release_dates
        $totalGames = DB::table('games')
            ->whereNotNull('release_dates')
            ->where('release_dates', '!=', 'null')
            ->count();

        if ($totalGames === 0) {
            $this->info('No games with release_dates found in JSON field.');
            return 0;
        }

        // Check if any data already exists in target table
        $existingCount = GameReleaseDate::count();

        $this->newLine();
        $this->info('Release Dates Migration Summary:');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Games with JSON release_dates: <fg=cyan>{$totalGames}</>");
        $this->line("Existing records in game_release_dates: <fg=cyan>{$existingCount}</>");
        $this->line("Chunk size: <fg=cyan>{$chunkSize}</>");
        $mode = $dryRun ? '<fg=yellow>DRY RUN (no changes)</>' : '<fg=green>LIVE</>';
        $this->line("Mode: {$mode}");
        $this->newLine();

        // Confirmation prompt (unless forced or dry-run)
        if (!$force && !$dryRun) {
            if (!$this->confirm('Do you want to proceed with the migration?', true)) {
                $this->warn('Migration cancelled.');
                return 1;
            }
        }

        $this->info('Starting migration...');
        $this->newLine();

        // Progress bar
        $bar = $this->output->createProgressBar($totalGames);
        $bar->start();

        $processed = 0;
        $created = 0;
        $skipped = 0;
        $errors = 0;

        // Process in chunks to avoid memory issues
        DB::table('games')
            ->whereNotNull('release_dates')
            ->where('release_dates', '!=', 'null')
            ->orderBy('id')
            ->chunk($chunkSize, function ($games) use (&$processed, &$created, &$skipped, &$errors, $bar, $dryRun) {
                foreach ($games as $game) {
                    try {
                        $releaseDates = json_decode($game->release_dates, true);

                        if (empty($releaseDates) || !is_array($releaseDates)) {
                            $skipped++;
                            $processed++;
                            $bar->advance();
                            continue;
                        }

                        foreach ($releaseDates as $releaseDate) {
                            // Find platform_id from IGDB platform ID
                            $platformId = null;
                            if (isset($releaseDate['platform'])) {
                                $platform = Platform::where('igdb_id', $releaseDate['platform'])->first();
                                $platformId = $platform?->id;
                            }

                            // Find status_id from IGDB status ID
                            $statusId = null;
                            if (isset($releaseDate['status'])) {
                                $status = ReleaseDateStatus::where('igdb_id', $releaseDate['status'])->first();
                                $statusId = $status?->id;
                            }

                            // Parse date
                            $date = null;
                            if (isset($releaseDate['date'])) {
                                try {
                                    $date = Carbon::createFromTimestamp($releaseDate['date']);
                                } catch (\Exception $e) {
                                    // Skip invalid dates
                                    continue;
                                }
                            }

                            // Check if already exists (by igdb_release_date_id)
                            $igdbId = $releaseDate['id'] ?? null;
                            if ($igdbId) {
                                $exists = GameReleaseDate::where('game_id', $game->id)
                                    ->where('igdb_release_date_id', $igdbId)
                                    ->exists();

                                if ($exists) {
                                    continue; // Skip if already migrated
                                }
                            }

                            if (!$dryRun) {
                                // Insert into game_release_dates
                                GameReleaseDate::create([
                                    'game_id' => $game->id,
                                    'platform_id' => $platformId,
                                    'status_id' => $statusId,
                                    'igdb_release_date_id' => $igdbId,
                                    'date' => $date,
                                    'year' => $releaseDate['y'] ?? null,
                                    'month' => $releaseDate['m'] ?? null,
                                    'day' => $releaseDate['d'] ?? null,
                                    'region' => $releaseDate['region'] ?? null,
                                    'human_readable' => $releaseDate['human'] ?? null,
                                    'is_manual' => false,
                                ]);
                            }

                            $created++;
                        }

                        $processed++;
                    } catch (\Exception $e) {
                        $errors++;
                        \Log::error("Error migrating release dates for game {$game->id}", [
                            'error' => $e->getMessage(),
                            'game_id' => $game->id,
                        ]);
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Migration completed!');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Games processed: <fg=cyan>{$processed}</>");
        $this->line("Release dates created: <fg=green>{$created}</>");
        $this->line("Games skipped (invalid data): <fg=yellow>{$skipped}</>");

        if ($errors > 0) {
            $this->line("Errors: <fg=red>{$errors}</> (check logs)");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a DRY RUN - no changes were made.');
            $this->info('Run without --dry-run to perform the actual migration.');
        }

        $this->newLine();

        return 0;
    }
}
