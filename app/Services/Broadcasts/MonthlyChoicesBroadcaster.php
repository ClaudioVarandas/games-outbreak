<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Services\Broadcasts\Channels\MonthlyBroadcastChannel;
use App\Services\Broadcasts\Exceptions\BroadcastFailedException;
use App\Services\MonthlyChoicesCollector;
use App\Services\MonthlyChoicesPayload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class MonthlyChoicesBroadcaster
{
    /**
     * @param  iterable<MonthlyBroadcastChannel>  $channels
     */
    public function __construct(
        private readonly MonthlyChoicesCollector $collector,
        private readonly iterable $channels,
    ) {}

    public function broadcast(
        ?CarbonImmutable $now = null,
        ?string $onlyChannel = null,
        bool $isPreview = false,
        bool $isCurrent = false,
    ): void {
        $payload = $isCurrent
            ? $this->collector->forCurrentMonth($now, $isPreview)
            : $this->collector->forUpcomingMonth($now, $isPreview);

        $logContext = [
            'is_preview' => $isPreview,
            'is_current' => $isCurrent,
        ];

        if ($payload->isEmpty()) {
            Log::info('monthly-choices.skipped', [
                ...$logContext,
                'reason' => 'empty-window',
                'window_start' => $payload->windowStart->toDateString(),
                'window_end' => $payload->windowEnd->toDateString(),
            ]);

            return;
        }

        $targets = $this->selectChannels($onlyChannel);

        if ($targets === []) {
            Log::info('monthly-choices.skipped', [
                ...$logContext,
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
                Log::error('monthly-choices.channel.failed', [
                    ...$logContext,
                    'channel' => $channel->name(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($failures === []) {
            Log::info('monthly-choices.broadcast.ok', [
                ...$logContext,
                'channels' => $sent,
                'games' => $payload->count(),
            ]);

            return;
        }

        if ($sent === []) {
            throw new BroadcastFailedException($failures);
        }

        Log::error('monthly-choices.broadcast.partial', [
            ...$logContext,
            'sent' => $sent,
            'failed' => array_keys($failures),
        ]);
    }

    /**
     * @return list<MonthlyBroadcastChannel>
     */
    public function channels(): array
    {
        return iterator_to_array($this->iterate(), false);
    }

    /**
     * @return array<string, array{enabled: bool, text: string}>
     */
    public function preview(MonthlyChoicesPayload $payload, ?string $onlyChannel = null): array
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
     * @return list<MonthlyBroadcastChannel>
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
     * @return iterable<MonthlyBroadcastChannel>
     */
    private function iterate(): iterable
    {
        foreach ($this->channels as $channel) {
            yield $channel;
        }
    }
}
