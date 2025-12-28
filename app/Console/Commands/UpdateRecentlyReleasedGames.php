<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateRecentlyReleasedGames extends Command
{
    protected $signature = 'igdb:update-recently-released
                            {--days=60 : Number of days back to consider (default 60)}
                            {--limit=100 : Maximum games to update (default 100)}
                            {--force : Force update even if recently synced}';

    protected $description = 'Update games released in the last N days to keep post-release data fresh';

    public function handle(IgdbService $igdb): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $force = $this->option('force');

        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $this->info("Updating recently released games (last {$days} days)...");
        $this->info("Date range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        // Query games released in date range
        $query = Game::whereBetween('first_release_date', [$startDate, $endDate])
            ->orderByDesc('update_priority')
            ->orderBy('last_igdb_sync_at', 'asc');

        // Don't re-sync games updated in last 7 days unless forced
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('last_igdb_sync_at')
                    ->orWhere('last_igdb_sync_at', '<=', Carbon::now()->subDays(7));
            });
        }

        $games = $query->limit($limit)->get();

        if ($games->isEmpty()) {
            $this->info('No games need updating.');
            return self::SUCCESS;
        }

        $this->info("Found {$games->count()} game(s) to update");

        $bar = $this->output->createProgressBar($games->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($games as $game) {
            try {
                // Check if update is needed
                if (!$force && !$game->shouldUpdate(7)) {
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

                // Rate limiting: small delay between requests
                usleep(300000); // 0.3 seconds

                $bar->advance();

            } catch (\Exception $e) {
                $this->error("\nFailed to update game {$game->name} (ID: {$game->igdb_id}): " . $e->getMessage());
                $failed++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Update complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Failed', $failed],
            ]
        );

        return self::SUCCESS;
    }
}
