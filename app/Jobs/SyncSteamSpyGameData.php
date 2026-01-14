<?php

namespace App\Jobs;

use App\Models\GameExternalSource;
use App\Services\SteamSpyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncSteamSpyGameData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $gameExternalSourceId,
        public ?int $nextGameExternalSourceId = null,
    ) {
        $this->onQueue('low');
    }

    public function handle(SteamSpyService $steamSpy): void
    {
        $sourceLink = GameExternalSource::with(['game', 'externalGameSource'])->find($this->gameExternalSourceId);

        if (! $sourceLink) {
            \Log::warning('SyncSteamSpyGameData: Source link not found', [
                'id' => $this->gameExternalSourceId,
            ]);
            $this->dispatchNext();

            return;
        }

        $steamSpy->syncGameData($sourceLink);

        $this->dispatchNext();
    }

    public function failed(Throwable $exception): void
    {
        $sourceLink = GameExternalSource::find($this->gameExternalSourceId);

        if ($sourceLink) {
            $sourceLink->markAsFailed();
        }

        \Log::error('SyncSteamSpyGameData job failed', [
            'id' => $this->gameExternalSourceId,
            'error' => $exception->getMessage(),
        ]);

        $this->dispatchNext();
    }

    private function dispatchNext(): void
    {
        if ($this->nextGameExternalSourceId) {
            $nextSourceLink = GameExternalSource::find($this->nextGameExternalSourceId);

            if ($nextSourceLink) {
                $followingId = GameExternalSource::query()
                    ->forSource(1) // Steam
                    ->where('id', '>', $this->nextGameExternalSourceId)
                    ->orderBy('id')
                    ->value('id');

                self::dispatch($this->nextGameExternalSourceId, $followingId);
            }
        }
    }
}
