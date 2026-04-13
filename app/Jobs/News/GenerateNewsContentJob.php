<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\GenerateLocalizedNewsContent;
use App\Models\NewsImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateNewsContentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Exponential backoff: 60s, 120s, 240s between retries.
     * Needed for OpenAI free-tier rate limits (3 RPM).
     */
    public function backoff(): array
    {
        return [60, 120, 240];
    }

    public function __construct(
        public readonly NewsImport $import
    ) {}

    public function handle(GenerateLocalizedNewsContent $action): void
    {
        $action->handle($this->import);
    }
}
