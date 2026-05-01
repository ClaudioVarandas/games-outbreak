<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BroadcastMonthlyChoicesJob;
use App\Services\Broadcasts\MonthlyChoicesBroadcaster;
use App\Services\MonthlyChoicesCollector;
use Illuminate\Console\Command;

class BroadcastMonthlyChoicesCommand extends Command
{
    protected $signature = 'monthly-choices:broadcast
                            {--dry-run : Render payload locally without calling any APIs}
                            {--channel=telegram : Limit to one channel (telegram|all). Default: telegram.}
                            {--preview : Mark this run as the early-month PREVIEW broadcast}';

    protected $description = 'Broadcast next month\'s curated releases to configured channels (Telegram). X not wired yet.';

    public function handle(
        MonthlyChoicesCollector $collector,
        MonthlyChoicesBroadcaster $broadcaster,
    ): int {
        $channel = $this->option('channel');
        $isPreview = (bool) $this->option('preview');

        if ($channel !== null && ! in_array($channel, ['telegram', 'all'], true)) {
            $this->error("Unknown --channel={$channel}. Expected one of: telegram, all.");

            return self::INVALID;
        }

        if ($this->option('dry-run')) {
            $payload = $collector->forUpcomingMonth(null, $isPreview);
            $this->info(sprintf(
                'Upcoming window: %s → %s · %d games%s',
                $payload->windowStart->toDateString(),
                $payload->windowEnd->toDateString(),
                $payload->count(),
                $isPreview ? ' · PREVIEW' : '',
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

        BroadcastMonthlyChoicesJob::dispatchSync(
            $channel === 'all' ? null : $channel,
            $isPreview,
        );

        $this->info('Broadcast completed.');

        return self::SUCCESS;
    }
}
