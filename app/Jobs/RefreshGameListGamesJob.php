<?php

namespace App\Jobs;

use App\Models\GameList;
use App\Services\GameListRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshGameListGamesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $gameListId,
        public bool $force = false
    ) {
        $this->onQueue('low');
    }

    public function handle(GameListRefreshService $refreshService): void
    {
        $gameList = GameList::with('games')->find($this->gameListId);

        if (! $gameList) {
            Log::warning("RefreshGameListGamesJob: GameList {$this->gameListId} not found");

            return;
        }

        $refreshService->refreshList($gameList, $this->force);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RefreshGameListGamesJob failed for game list ID {$this->gameListId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
