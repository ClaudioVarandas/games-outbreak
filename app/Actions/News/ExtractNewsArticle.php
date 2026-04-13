<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Contracts\ContentExtractorInterface;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use Illuminate\Support\Facades\Log;

class ExtractNewsArticle
{
    public function __construct(
        private readonly ContentExtractorInterface $extractor
    ) {}

    public function handle(NewsImport $import): void
    {
        $import->markAs(NewsImportStatusEnum::Fetching);

        try {
            $data = $this->extractor->extract($import->url);

            $import->update([
                'status' => NewsImportStatusEnum::Extracted,
                'raw_title' => $data['title'] ?? null,
                'raw_body' => $data['content'] ?? null,
                'raw_excerpt' => $data['summary'] ?? null,
                'raw_image_url' => $data['image'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('News extraction failed', ['url' => $import->url, 'error' => $e->getMessage()]);
            $import->markAs(NewsImportStatusEnum::Failed, $e->getMessage());
        }
    }
}
