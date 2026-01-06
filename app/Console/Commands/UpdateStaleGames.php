<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateStaleGames extends Command
{
    protected $signature = 'igdb:update-stale
                            {--min-days=90 : Minimum days since last sync to be considered stale (default 90)}
                            {--batch-size=50 : Number of games to update per batch (default 50)}
                            {--force : Force update regardless of stale threshold}';

    protected $description = 'Update games that have not been synced in a long time';

    public function handle(IgdbService $igdb): int
    {
        $minDays = max(1, (int) $this->option('min-days'));
        $batchSize = max(1, (int) $this->option('batch-size'));
        $force = $this->option('force');

        $this->info("Updating stale games (not synced for {$minDays}+ days)...");

        // Query stale games
        $query = Game::query();

        if (!$force) {
            $query->where(function ($q) use ($minDays) {
                $q->whereNull('last_igdb_sync_at')
                    ->orWhere('last_igdb_sync_at', '<=', Carbon::now()->subDays($minDays));
            });
        }

        // Prioritize by update priority and view count
        $query->orderByDesc('update_priority')
            ->orderByDesc('view_count')
            ->orderBy('last_igdb_sync_at', 'asc');

        $totalStale = $query->count();

        if ($totalStale === 0) {
            $this->info('No stale games found.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalStale} stale game(s)");
        $this->info("Processing batch of {$batchSize} games...");

        // Process only batch size
        $games = $query->limit($batchSize)->get();

        if ($games->isEmpty()) {
            $this->info('No games to process in this batch.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($games->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($games as $game) {
            try {
                // Check if update is needed
                if (!$force && !$game->shouldUpdate($minDays)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Refresh from IGDB
                $success = $game->refreshFromIgdb($igdb);

                if ($success) {
                    $updated++;
                } else {
                    $failed++;
                }

                // Rate limiting: longer delay for stale updates
                usleep(500000); // 0.5 seconds

                $bar->advance();

            } catch (\Exception $e) {
                $this->error("\nFailed to update game {$game->name} (ID: {$game->igdb_id}): " . $e->getMessage());
                $failed++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Batch update complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Failed', $failed],
                ['Remaining', max(0, $totalStale - $batchSize)],
            ]
        );

        if ($totalStale > $batchSize) {
            $remaining = $totalStale - $batchSize;
            $this->warn("Note: {$remaining} stale game(s) remain. Run this command again to process more.");
        }

        return self::SUCCESS;
    }
}
