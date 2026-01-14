<?php

namespace App\Console\Commands;

use App\Jobs\SyncSteamSpyGameData;
use App\Models\GameExternalSource;
use App\Services\SteamSpyService;
use Illuminate\Console\Command;

class SteamSpySync extends Command
{
    protected $signature = 'steamspy:sync
                            {--threshold=0 : Minimum update_priority score to sync}
                            {--limit=500 : Maximum number of games to process}';

    protected $description = 'Sync game data from SteamSpy API via background jobs';

    public function handle(SteamSpyService $steamSpy): int
    {
        $threshold = (int) $this->option('threshold');
        $limit = (int) $this->option('limit');

        $this->info('Querying games with Steam source for SteamSpy sync...');
        $this->info("Priority threshold: {$threshold}");
        $this->info("Limit: {$limit}");

        $query = GameExternalSource::query()
            ->forSource(1) // Steam (IGDB category 1)
            ->with(['game', 'externalGameSource'])
            ->whereHas('game', function ($q) use ($threshold) {
                $q->where('update_priority', '>=', $threshold);
            })
            ->orderByDesc(function ($q) {
                $q->select('update_priority')
                    ->from('games')
                    ->whereColumn('games.id', 'game_external_sources.game_id');
            });

        // Filter for stale entries
        $sourceLinks = $query->get()->filter(function ($sourceLink) use ($steamSpy) {
            return $steamSpy->isStale($sourceLink->game, $sourceLink);
        })->take($limit);

        $count = $sourceLinks->count();

        if ($count === 0) {
            $this->warn('No games eligible for SteamSpy sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} games eligible for sync.");

        // Get first two IDs for chain
        $firstSourceLink = $sourceLinks->first();
        $secondSourceLink = $sourceLinks->skip(1)->first();

        $firstId = $firstSourceLink->id;
        $secondId = $secondSourceLink?->id;

        // Dispatch first job which will chain the rest
        SyncSteamSpyGameData::dispatch($firstId, $secondId);

        $this->info("Dispatched SteamSpy sync job chain starting with ID {$firstId}");
        $this->info('Jobs will process on the "low" queue.');

        return self::SUCCESS;
    }
}
