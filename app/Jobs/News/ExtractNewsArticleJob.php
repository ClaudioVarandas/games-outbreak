<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\ExtractNewsArticle;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractNewsArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly NewsImport $import
    ) {}

    public function handle(ExtractNewsArticle $action): void
    {
        $action->handle($this->import);

        if ($this->import->fresh()->status === NewsImportStatusEnum::Extracted) {
            GenerateNewsContentJob::dispatch($this->import);
        }
    }
}
