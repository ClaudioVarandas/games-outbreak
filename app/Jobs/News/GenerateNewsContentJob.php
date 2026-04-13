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

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly NewsImport $import
    ) {}

    public function handle(GenerateLocalizedNewsContent $action): void
    {
        $action->handle($this->import);
    }
}
