<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\IgdbService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchGameImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $gameId,
        public string $gameName,
        public ?int $steamAppId = null,
        public ?int $igdbId = null,
        public array $imageTypes = ['cover', 'hero', 'logo']
    ) {}

    /**
     * Execute the job.
     */
    public function handle(IgdbService $igdbService): void
    {
        $game = Game::find($this->gameId);

        if (! $game) {
            \Log::warning("FetchGameImages: Game {$this->gameId} not found");

            return;
        }

        // Get Steam AppID using dual lookup: constructor param -> external sources -> steam_data (deprecated)
        $steamAppId = $this->steamAppId;
        if (! $steamAppId) {
            $steamAppId = $igdbService->getSteamAppIdFromSources($game);
        }
        if (! $steamAppId && ! empty($game->steam_data['appid'])) {
            $steamAppId = (int) $game->steam_data['appid'];
        }

        $updates = [];

        foreach ($this->imageTypes as $type) {
            // Skip if image already exists
            $imageField = match ($type) {
                'cover' => 'cover_image_id',
                'hero' => 'hero_image_id',
                'logo' => 'logo_image_id',
                default => null,
            };

            if (! $imageField) {
                continue;
            }

            // Only fetch if the field is null or empty
            if (! empty($game->$imageField)) {
                continue;
            }

            // Fetch image from SteamGridDB
            $imageId = $igdbService->fetchImageFromSteamGridDb(
                $this->gameName,
                $type,
                $steamAppId,
                $this->igdbId
            );

            if ($imageId) {
                $updates[$imageField] = $imageId;
            }
        }

        // Update game record if any images were fetched
        if (! empty($updates)) {
            $game->update($updates);
            \Log::info("FetchGameImages: Updated images for game {$this->gameId}", $updates);
        }
    }
}
