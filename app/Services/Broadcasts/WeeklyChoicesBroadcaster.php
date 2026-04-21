<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Services\Broadcasts\Channels\BroadcastChannel;
use App\Services\Broadcasts\Exceptions\BroadcastFailedException;
use App\Services\WeeklyChoicesCollector;
use App\Services\WeeklyChoicesPayload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeeklyChoicesBroadcaster
{
    /**
     * @param  iterable<BroadcastChannel>  $channels
     */
    public function __construct(
        private readonly WeeklyChoicesCollector $collector,
        private readonly iterable $channels,
    ) {}

    public function broadcast(?CarbonImmutable $now = null, ?string $onlyChannel = null): void
    {
        $payload = $this->collector->forUpcomingWeek($now);

        if ($payload->isEmpty()) {
            Log::info('weekly-choices.skipped', [
                'reason' => 'empty-window',
                'window_start' => $payload->windowStart->toDateString(),
                'window_end' => $payload->windowEnd->toDateString(),
            ]);

            return;
        }

        $targets = $this->selectChannels($onlyChannel);

        if ($targets === []) {
            Log::info('weekly-choices.skipped', [
                'reason' => 'no-enabled-channels',
                'requested' => $onlyChannel,
            ]);

            return;
        }

        $failures = [];
        $sent = [];

        foreach ($targets as $channel) {
            try {
                $channel->send($payload);
                $sent[] = $channel->name();
            } catch (Throwable $e) {
                $failures[$channel->name()] = $e;
                Log::error('weekly-choices.channel.failed', [
                    'channel' => $channel->name(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($failures === []) {
            Log::info('weekly-choices.broadcast.ok', [
                'channels' => $sent,
                'games' => $payload->count(),
            ]);

            return;
        }

        if ($sent === []) {
            throw new BroadcastFailedException($failures);
        }

        Log::error('weekly-choices.broadcast.partial', [
            'sent' => $sent,
            'failed' => array_keys($failures),
        ]);
    }

    /**
     * @return list<BroadcastChannel>
     */
    public function channels(): array
    {
        return iterator_to_array($this->iterate(), false);
    }

    public function preview(WeeklyChoicesPayload $payload, ?string $onlyChannel = null): array
    {
        $result = [];
        foreach ($this->selectChannels($onlyChannel, includeDisabled: true) as $channel) {
            $result[$channel->name()] = [
                'enabled' => $channel->isEnabled(),
                'text' => $channel->preview($payload),
            ];
        }

        return $result;
    }

    /**
     * @return list<BroadcastChannel>
     */
    private function selectChannels(?string $onlyChannel, bool $includeDisabled = false): array
    {
        $selected = [];
        foreach ($this->iterate() as $channel) {
            if ($onlyChannel !== null && $onlyChannel !== 'all' && $channel->name() !== $onlyChannel) {
                continue;
            }

            if (! $includeDisabled && ! $channel->isEnabled()) {
                continue;
            }

            $selected[] = $channel;
        }

        return $selected;
    }

    /**
     * @return iterable<BroadcastChannel>
     */
    private function iterate(): iterable
    {
        foreach ($this->channels as $channel) {
            yield $channel;
        }
    }
}
