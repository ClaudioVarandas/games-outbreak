<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdatePopularGames extends Command
{
    protected $signature = 'igdb:update-popular
                            {--limit=100 : Maximum games to update (default 100)}
                            {--min-views=5 : Minimum view count to be considered popular (default 5)}
                            {--force : Force update even if recently synced}';

    protected $description = 'Update popular games based on view count and engagement';

    public function handle(IgdbService $igdb): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $minViews = max(1, (int) $this->option('min-views'));
        $force = $this->option('force');

        $this->info("Updating popular games (min {$minViews} views, limit {$limit})...");

        // Query popular games with highest priority scores
        $query = Game::where('view_count', '>=', $minViews)
            ->orderByDesc('update_priority')
            ->orderByDesc('view_count')
            ->orderBy('last_igdb_sync_at', 'asc');

        // Don't re-sync games updated in last 14 days unless forced
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('last_igdb_sync_at')
                    ->orWhere('last_igdb_sync_at', '<=', Carbon::now()->subDays(14));
            });
        }

        $games = $query->limit($limit)->get();

        if ($games->isEmpty()) {
            $this->info('No popular games need updating.');
            return self::SUCCESS;
        }

        $this->info("Found {$games->count()} popular game(s) to update");

        // Display top games
        $this->table(
            ['Name', 'Views', 'Priority', 'Last Sync'],
            $games->take(10)->map(fn($g) => [
                $g->name,
                $g->view_count,
                $g->update_priority,
                $g->last_igdb_sync_at ? $g->last_igdb_sync_at->diffForHumans() : 'Never'
            ])
        );

        if (!$this->confirm('Proceed with update?', true)) {
            $this->info('Update cancelled.');
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
                if (!$force && !$game->shouldUpdate(14)) {
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
