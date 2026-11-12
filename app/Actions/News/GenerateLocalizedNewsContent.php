<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Contracts\NewsGenerationServiceInterface;
use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsImportStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateLocalizedNewsContent
{
    public function __construct(
        private readonly NewsGenerationServiceInterface $ai
    ) {}

    /**
     * Idempotent per import: an import owns at most one article, so a retried job
     * (e.g. `queue:retry`) returns the existing article instead of calling the AI again.
     * The article and its localizations are written in one transaction, so a failure
     * leaves nothing behind for the retry to trip over.
     */
    public function handle(NewsImport $import): NewsArticle
    {
        $existing = $import->article()->first();

        if ($existing) {
            $import->markAs(NewsImportStatusEnum::Ready);

            return $existing;
        }

        $import->markAs(NewsImportStatusEnum::Generating);

        try {
            $localized = $this->ai->summarizeAndLocalize([
                'title' => $import->raw_title ?? '',
                'content' => $import->raw_body ?? '',
                'source' => $import->source_domain ?? 'Unknown',
            ]);

            $provider = config('services.news_ai_provider', 'anthropic');
            $metadata = [
                'provider' => $provider,
                'model' => config("services.{$provider}.model"),
            ];

            Log::debug('GenerateLocalizedNewsContent: localized keys received', [
                'keys' => array_keys($localized),
                'import_id' => $import->id,
            ]);

            $article = DB::transaction(function () use ($import, $localized, $metadata): NewsArticle {
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

                    Log::debug('GenerateLocalizedNewsContent: processing locale', [
                        'locale' => $locale->value,
                        'found' => ! is_null($data),
                        'title' => $data['title'] ?? null,
                    ]);

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
                        'generation_metadata' => $metadata,
                    ]);
                }

                return $article;
            });

            $import->markAs(NewsImportStatusEnum::Ready);

            return $article;
        } catch (\Throwable $e) {
            Log::error('News generation failed', ['import_id' => $import->id, 'error' => $e->getMessage()]);
            $import->markAs(NewsImportStatusEnum::Failed, $e->getMessage());
            throw $e;
        }
    }
}
