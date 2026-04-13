<?php

use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsImportStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use App\Models\NewsImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('news_import belongs to user and has one article', function () {
    $import = NewsImport::factory()->ready()->create();
    $article = NewsArticle::factory()->create(['news_import_id' => $import->id]);

    expect($import->article->id)->toBe($article->id);
    expect($import->user)->not->toBeNull();
});

it('news_article has many localizations', function () {
    $article = NewsArticle::factory()->create();
    NewsArticleLocalization::factory()->create(['news_article_id' => $article->id, 'locale' => NewsLocaleEnum::PtPt]);
    NewsArticleLocalization::factory()->ptBr()->create(['news_article_id' => $article->id]);

    $article->load('localizations');

    expect($article->localizations)->toHaveCount(2);
    expect($article->localization('pt-PT'))->not->toBeNull();
    expect($article->localization('pt-BR'))->not->toBeNull();
});

it('markAs updates status and failure_reason', function () {
    $import = NewsImport::factory()->create();
    $import->markAs(NewsImportStatusEnum::Failed, 'Timeout');

    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Failed);
    expect($import->fresh()->failure_reason)->toBe('Timeout');
});

it('scopeScheduledDue returns only past-due scheduled articles', function () {
    $due = NewsArticle::factory()->scheduled()->create(['scheduled_at' => now()->subMinute()]);
    $future = NewsArticle::factory()->scheduled()->create(['scheduled_at' => now()->addHour()]);

    $results = NewsArticle::scheduledDue()->get();

    expect($results->contains($due))->toBeTrue();
    expect($results->contains($future))->toBeFalse();
});

it('auto-generates unique slugs on create', function () {
    $a = NewsArticle::factory()->create(['original_title' => 'Test Game Released', 'slug_pt_pt' => null, 'slug_pt_br' => null]);
    $b = NewsArticle::factory()->create(['original_title' => 'Test Game Released', 'slug_pt_pt' => null, 'slug_pt_br' => null]);

    expect($a->slug_pt_pt)->not->toBe($b->slug_pt_pt);
});

it('isFailed and isReady helpers return correct values', function () {
    $failed = NewsImport::factory()->failed()->create();
    $ready = NewsImport::factory()->ready()->create();

    expect($failed->isFailed())->toBeTrue();
    expect($failed->isReady())->toBeFalse();
    expect($ready->isReady())->toBeTrue();
    expect($ready->isFailed())->toBeFalse();
});

it('isPubliclyVisible returns true only for Published status', function () {
    expect(NewsArticleStatusEnum::Published->isPubliclyVisible())->toBeTrue();
    expect(NewsArticleStatusEnum::Review->isPubliclyVisible())->toBeFalse();
    expect(NewsArticleStatusEnum::Draft->isPubliclyVisible())->toBeFalse();
});
