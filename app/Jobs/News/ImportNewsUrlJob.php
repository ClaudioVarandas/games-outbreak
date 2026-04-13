<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\CreateNewsImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportNewsUrlJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $url,
        public readonly int $userId
    ) {}

    public function handle(CreateNewsImport $action): void
    {
        $import = $action->handle($this->url, $this->userId);

        ExtractNewsArticleJob::dispatch($import);
    }
}
