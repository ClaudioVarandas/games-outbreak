<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BroadcastWeeklyChoicesJob;
use App\Services\Broadcasts\WeeklyChoicesBroadcaster;
use App\Services\WeeklyChoicesCollector;
use Illuminate\Console\Command;

class BroadcastWeeklyChoicesCommand extends Command
{
    protected $signature = 'weekly-choices:broadcast
                            {--dry-run : Render payload locally without calling any APIs}
                            {--channel= : Limit to one channel (telegram|x|all). Default: all.}';

    protected $description = 'Broadcast next week\'s curated releases to configured channels (Telegram, X).';

    public function handle(
        WeeklyChoicesCollector $collector,
        WeeklyChoicesBroadcaster $broadcaster,
    ): int {
        $channel = $this->option('channel');

        if ($channel !== null && ! in_array($channel, ['telegram', 'x', 'all'], true)) {
            $this->error("Unknown --channel={$channel}. Expected one of: telegram, x, all.");

            return self::INVALID;
        }

        if ($this->option('dry-run')) {
            $payload = $collector->forUpcomingWeek();
            $this->info(sprintf(
                'Upcoming window: %s → %s · %d games',
                $payload->windowStart->toDateString(),
                $payload->windowEnd->toDateString(),
                $payload->count(),
            ));

            if ($payload->isEmpty()) {
                $this->warn('Payload is empty — nothing would be sent.');

                return self::SUCCESS;
            }

            foreach ($broadcaster->preview($payload, $channel) as $name => $info) {
                $state = $info['enabled'] ? 'enabled' : 'disabled';
                $this->line('');
                $this->line("──── {$name} ({$state}) ────");
                $this->line($info['text']);
            }

            return self::SUCCESS;
        }

        BroadcastWeeklyChoicesJob::dispatchSync($channel === 'all' ? null : $channel);

        $this->info('Broadcast completed.');

        return self::SUCCESS;
    }
}
