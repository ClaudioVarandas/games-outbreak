<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncSteamReviewScores;
use App\Models\GameExternalSource;
use App\Services\SteamStoreService;
use Illuminate\Console\Command;

class SteamReviewSync extends Command
{
    protected $signature = 'steam:review-sync
                            {--limit=200 : Maximum number of games to dispatch}';

    protected $description = 'Sync Metacritic + Steam review scores for games with a Steam link via background jobs';

    public function handle(SteamStoreService $steamStore): int
    {
        $limit = (int) $this->option('limit');

        $this->info('Querying games with Steam source for review-score sync...');

        // Coarse pre-filter: exclude far-future releases (no scores exist yet).
        $games = GameExternalSource::query()
            ->forSource(1) // Steam (IGDB category 1)
            ->whereHas('game', function ($q) {
                $q->whereNull('first_release_date')
                    ->orWhere('first_release_date', '<=', now()->addDays(7));
            })
            ->with('game')
            ->orderByDesc(function ($q) {
                $q->select('update_priority')
                    ->from('games')
                    ->whereColumn('games.id', 'game_external_sources.game_id');
            })
            ->get()
            ->pluck('game')
            ->filter()
            ->filter(fn ($game) => $steamStore->reviewScoresAreStale($game))
            ->take($limit)
            ->values();

        if ($games->isEmpty()) {
            $this->warn('No games eligible for review-score sync.');

            return self::SUCCESS;
        }

        // Stagger dispatch to avoid hammering the Steam storefront.
        $games->each(function ($game, int $index) {
            SyncSteamReviewScores::dispatch($game->id)->delay(now()->addSeconds($index * 2));
        });

        $this->info("Dispatched {$games->count()} review-score sync job(s) on the \"low\" queue.");

        return self::SUCCESS;
    }
}
