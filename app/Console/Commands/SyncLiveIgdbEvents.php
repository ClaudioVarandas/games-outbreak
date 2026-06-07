<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameList;
use App\Services\ChannelTrailerService;
use App\Services\EventImportService;
use Illuminate\Console\Command;

class SyncLiveIgdbEvents extends Command
{
    protected $signature = 'igdb:events:sync-live';

    protected $description = 'Re-sync games for every imported events list within its live window (start_at .. start_at + services.igdb.event_sync_window_hours). Picks up games IGDB adds during the event.';

    public function handle(EventImportService $service, ChannelTrailerService $channelTrailers): int
    {
        $capHours = (int) config('services.igdb.event_sync_window_hours', 3);

        $lists = GameList::events()
            ->whereNotNull('igdb_event_id')
            ->where('start_at', '<=', now())
            ->where('start_at', '>=', now()->subHours($capHours))
            ->get();

        if ($lists->isEmpty()) {
            $this->info('No live events to sync.');

            return self::SUCCESS;
        }

        foreach ($lists as $list) {
            $event = $service->fetchEvent($list->igdb_event_id);

            if ($event === null) {
                $this->warn("IGDB event #{$list->igdb_event_id} ({$list->name}) not found — skipping.");

                continue;
            }

            $report = $service->syncGames($list, $event);

            $matchedTrailers = 0;
            try {
                $matchedTrailers = $channelTrailers->syncFromChannel($list)['matched'];
            } catch (\Throwable $e) {
                $this->warn("  Channel trailer match failed for {$list->name}: {$e->getMessage()}");
            }

            $this->info(sprintf(
                '%s: added %d, skipped %d, failed %d. Channel trailers matched: %d.',
                $list->name,
                $report['added'],
                $report['skipped'],
                $report['failed'],
                $matchedTrailers,
            ));
        }

        return self::SUCCESS;
    }
}
