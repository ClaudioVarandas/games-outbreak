<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Broadcasts\MonthlyChoicesBroadcaster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastMonthlyChoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly ?string $onlyChannel = null,
        public readonly bool $isPreview = false,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MonthlyChoicesBroadcaster $broadcaster): void
    {
        $broadcaster->broadcast(onlyChannel: $this->onlyChannel, isPreview: $this->isPreview);
    }

    public function failed(Throwable $e): void
    {
        Log::error('monthly-choices.broadcast.failed', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'channel' => $this->onlyChannel,
            'is_preview' => $this->isPreview,
        ]);
    }
}
