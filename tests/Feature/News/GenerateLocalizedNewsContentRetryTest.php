<?php

use App\Actions\News\GenerateLocalizedNewsContent;
use App\Contracts\NewsGenerationServiceInterface;
use App\Enums\NewsImportStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use App\Models\NewsImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function localizedPayload(): array
{
    return collect(NewsLocaleEnum::cases())
        ->mapWithKeys(fn (NewsLocaleEnum $locale) => [$locale->value => [
            'title' => "Title {$locale->value}",
            'summary_short' => 'Short.',
            'summary_medium' => 'Medium.',
            'body' => ['type' => 'doc', 'content' => []],
            'seo_title' => 'SEO',
            'seo_description' => 'Description.',
        ]])
        ->all();
}

it('leaves no article behind and marks the import failed when a localization insert throws', function () {
    $import = NewsImport::factory()->extracted()->create();

    $ai = Mockery::mock(NewsGenerationServiceInterface::class);
    $ai->shouldReceive('summarizeAndLocalize')->once()->andReturn(localizedPayload());

    NewsArticleLocalization::creating(function (NewsArticleLocalization $localization) {
        if ($localization->locale === NewsLocaleEnum::PtPt) {
            throw new RuntimeException('Localization insert failed');
        }
    });

    expect(fn () => (new GenerateLocalizedNewsContent($ai))->handle($import))
        ->toThrow(RuntimeException::class, 'Localization insert failed');

    expect(NewsArticle::where('news_import_id', $import->id)->count())->toBe(0);
    expect(NewsArticleLocalization::count())->toBe(0);
    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Failed);
    expect($import->fresh()->failure_reason)->toBe('Localization insert failed');
});

it('creates a single article when run twice for the same import', function () {
    $import = NewsImport::factory()->extracted()->create();

    $ai = Mockery::mock(NewsGenerationServiceInterface::class);
    $ai->shouldReceive('summarizeAndLocalize')->once()->andReturn(localizedPayload());

    $action = new GenerateLocalizedNewsContent($ai);
    $first = $action->handle($import);
    $second = $action->handle($import->fresh());

    expect($second->id)->toBe($first->id);
    expect(NewsArticle::where('news_import_id', $import->id)->count())->toBe(1);
    expect($first->localizations()->count())->toBe(count(NewsLocaleEnum::cases()));
    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Ready);
});

it('generates the article on retry after a failed attempt', function () {
    $import = NewsImport::factory()->extracted()->create();

    $ai = Mockery::mock(NewsGenerationServiceInterface::class);
    $ai->shouldReceive('summarizeAndLocalize')
        ->twice()
        ->andReturn(['en' => ['title' => 'Incomplete']], localizedPayload());

    $action = new GenerateLocalizedNewsContent($ai);

    expect(fn () => $action->handle($import))->toThrow(ErrorException::class, 'Undefined array key "summary_short"');
    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Failed);

    $article = $action->handle($import->fresh());

    expect(NewsArticle::where('news_import_id', $import->id)->count())->toBe(1);
    expect($article->localizations()->count())->toBe(count(NewsLocaleEnum::cases()));
    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Ready);
});
