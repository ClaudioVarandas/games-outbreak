<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BroadcastMonthlyChoicesJob;
use App\Services\Broadcasts\MonthlyChoicesBroadcaster;
use App\Services\MonthlyChoicesCollector;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BroadcastMonthlyChoicesCommand extends Command
{
    protected $signature = 'monthly-choices:broadcast
                            {--dry-run : Render payload locally without calling any APIs}
                            {--channel=telegram : Limit to one channel (telegram|all). Default: telegram.}
                            {--preview : Mark this run as the early-month PREVIEW broadcast}
                            {--current : Target the current calendar month instead of the upcoming one}
                            {--month= : Target an explicit month, format YYYY-MM (mutually exclusive with --current)}';

    protected $description = 'Broadcast curated monthly releases to configured channels (Telegram). X not wired yet.';

    public function handle(
        MonthlyChoicesCollector $collector,
        MonthlyChoicesBroadcaster $broadcaster,
    ): int {
        $channel = $this->option('channel');
        $isPreview = (bool) $this->option('preview');
        $isCurrent = (bool) $this->option('current');
        $monthOverride = $this->option('month') ?: null;

        if ($channel !== null && ! in_array($channel, ['telegram', 'all'], true)) {
            $this->error("Unknown --channel={$channel}. Expected one of: telegram, all.");

            return self::INVALID;
        }

        if ($monthOverride !== null && $isCurrent) {
            $this->error('--month and --current are mutually exclusive.');

            return self::INVALID;
        }

        $monthStart = null;

        if ($monthOverride !== null) {
            try {
                $monthStart = MonthlyChoicesBroadcaster::parseMonthOverride($monthOverride);
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::INVALID;
            }
        }

        if ($this->option('dry-run')) {
            $payload = match (true) {
                $monthStart instanceof CarbonImmutable => $collector->forMonth($monthStart, null, $isPreview),
                $isCurrent => $collector->forCurrentMonth(null, $isPreview),
                default => $collector->forUpcomingMonth(null, $isPreview),
            };

            $this->info(sprintf(
                '%s window: %s → %s · %d games%s',
                $monthOverride !== null ? 'Override' : ($isCurrent ? 'Current' : 'Upcoming'),
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
            $isCurrent,
            $monthOverride,
        );

        $this->info('Broadcast completed.');

        return self::SUCCESS;
    }
}
