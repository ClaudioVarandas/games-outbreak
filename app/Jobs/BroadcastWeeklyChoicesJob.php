<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Broadcasts\WeeklyChoicesBroadcaster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastWeeklyChoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly ?string $onlyChannel = null) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WeeklyChoicesBroadcaster $broadcaster): void
    {
        $broadcaster->broadcast(onlyChannel: $this->onlyChannel);
    }

    public function failed(Throwable $e): void
    {
        Log::error('weekly-choices.broadcast.failed', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'channel' => $this->onlyChannel,
        ]);
    }
}
