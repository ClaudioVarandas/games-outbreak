<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use App\Services\IgdbService;
use App\Services\SteamStoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncSteamReviewScores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $gameId)
    {
        $this->onQueue('low');
    }

    public function handle(SteamStoreService $steamStore, IgdbService $igdb): void
    {
        $game = Game::find($this->gameId);

        if (! $game) {
            \Log::warning('SyncSteamReviewScores: Game not found', ['game_id' => $this->gameId]);

            return;
        }

        $steamAppId = $igdb->getSteamAppIdFromSources($game);

        if (! $steamAppId) {
            return;
        }

        $steamStore->syncScores($game, (int) $steamAppId);
    }

    public function failed(Throwable $exception): void
    {
        \Log::error('SyncSteamReviewScores job failed', [
            'game_id' => $this->gameId,
            'error' => $exception->getMessage(),
        ]);
    }
}
