<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\IgdbService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshGameData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $gameId,
        public bool $force = false
    ) {
        // Set low priority queue for background updates
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(IgdbService $igdbService): void
    {
        $game = Game::find($this->gameId);

        if (!$game) {
            \Log::warning("RefreshGameData: Game {$this->gameId} not found");
            return;
        }

        // Check if update is needed (unless forced)
        if (!$this->force && !$game->shouldUpdate(30)) {
            \Log::info("RefreshGameData: Game {$game->name} (ID: {$game->igdb_id}) doesn't need update yet");
            return;
        }

        // Refresh game data from IGDB
        $success = $game->refreshFromIgdb($igdbService);

        if ($success) {
            \Log::info("RefreshGameData: Successfully refreshed game {$game->name} (ID: {$game->igdb_id})");
        } else {
            \Log::warning("RefreshGameData: Failed to refresh game {$game->name} (ID: {$game->igdb_id})");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error("RefreshGameData job failed for game ID {$this->gameId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
