<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Contracts\NewsGenerationServiceInterface;
use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsImportStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsImport;
use Illuminate\Support\Facades\Log;

class GenerateLocalizedNewsContent
{
    public function __construct(
        private readonly NewsGenerationServiceInterface $ai
    ) {}

    public function handle(NewsImport $import): NewsArticle
    {
        $import->markAs(NewsImportStatusEnum::Generating);

        try {
            $localized = $this->ai->summarizeAndLocalize([
                'title' => $import->raw_title ?? '',
                'content' => $import->raw_body ?? '',
                'source' => $import->source_domain ?? 'Unknown',
            ]);

            $article = NewsArticle::create([
                'news_import_id' => $import->id,
                'user_id' => $import->user_id,
                'status' => NewsArticleStatusEnum::Review,
                'source_name' => $import->source_domain,
                'source_url' => $import->url,
                'original_title' => $import->raw_title,
                'original_language' => 'en',
                'featured_image_url' => $import->raw_image_url,
            ]);

            foreach (NewsLocaleEnum::cases() as $locale) {
                $data = $localized[$locale->value] ?? null;
                if (! $data) {
                    continue;
                }

                $article->localizations()->create([
                    'locale' => $locale->value,
                    'title' => $data['title'],
                    'summary_short' => $data['summary_short'],
                    'summary_medium' => $data['summary_medium'],
                    'body' => $data['body'],
                    'seo_title' => $data['seo_title'],
                    'seo_description' => $data['seo_description'],
                    'generation_metadata' => ['model' => config('services.anthropic.model')],
                ]);
            }

            $import->markAs(NewsImportStatusEnum::Ready);

            return $article;
        } catch (\Throwable $e) {
            Log::error('News generation failed', ['import_id' => $import->id, 'error' => $e->getMessage()]);
            $import->markAs(NewsImportStatusEnum::Failed, $e->getMessage());
            throw $e;
        }
    }
}
