<?php

use App\Enums\NewsLocaleEnum;
use App\Jobs\Broadcasts\BroadcastNewsArticleJob;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.enabled' => true,
        'services.telegram.bot_token' => 'TOKEN',
        'services.telegram.chat_id' => 'CHAT',
    ]);
});

it('sends a photo with caption when the article has a featured image', function () {
    Http::fake([
        '*/sendPhoto' => Http::response(['ok' => true], 200),
    ]);

    $article = NewsArticle::factory()->published()->create([
        'original_title' => 'Sample headline',
        'featured_image_url' => 'https://img/hero.jpg',
    ]);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'Título em PT',
        'summary_short' => 'Resumo curto.',
    ]);

    BroadcastNewsArticleJob::dispatchSync($article->id);

    Http::assertSent(function ($req) {
        return str_contains($req->url(), '/sendPhoto')
            && $req['chat_id'] === 'CHAT'
            && $req['photo'] === 'https://img/hero.jpg'
            && $req['parse_mode'] === 'MarkdownV2'
            && str_contains($req['caption'], '📰')
            && str_contains($req['caption'], 'Ler mais');
    });

    expect($article->fresh()->broadcasted_at)->not->toBeNull();
});

it('falls back to sendMessage when there is no featured image', function () {
    Http::fake([
        '*/sendMessage' => Http::response(['ok' => true], 200),
    ]);

    $article = NewsArticle::factory()->published()->create([
        'featured_image_url' => null,
    ]);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'Título',
    ]);

    BroadcastNewsArticleJob::dispatchSync($article->id);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    expect($article->fresh()->broadcasted_at)->not->toBeNull();
});

it('skips when telegram is disabled', function () {
    config(['services.telegram.enabled' => false]);
    Http::fake();

    $article = NewsArticle::factory()->published()->create();
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
    ]);

    BroadcastNewsArticleJob::dispatchSync($article->id);

    Http::assertNothingSent();
    expect($article->fresh()->broadcasted_at)->toBeNull();
});

it('skips when should_broadcast is false', function () {
    Http::fake();

    $article = NewsArticle::factory()->published()->create(['should_broadcast' => false]);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
    ]);

    BroadcastNewsArticleJob::dispatchSync($article->id);

    Http::assertNothingSent();
});

it('skips when already broadcasted and not forced', function () {
    Http::fake();

    $article = NewsArticle::factory()->published()->create([
        'broadcasted_at' => now()->subHour(),
    ]);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
    ]);

    BroadcastNewsArticleJob::dispatchSync($article->id);

    Http::assertNothingSent();
});

it('forces a resend when $force is true', function () {
    Http::fake([
        '*/sendPhoto' => Http::response(['ok' => true], 200),
        '*/sendMessage' => Http::response(['ok' => true], 200),
    ]);

    $article = NewsArticle::factory()->published()->create([
        'broadcasted_at' => now()->subDay(),
        'featured_image_url' => 'https://img/x.jpg',
    ]);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
    ]);

    BroadcastNewsArticleJob::dispatchSync($article->id, force: true);

    Http::assertSentCount(1);
    expect($article->fresh()->broadcasted_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('prefers pt-PT then falls back to pt-BR and EN', function () {
    Http::fake(['*/sendMessage' => Http::response(['ok' => true], 200)]);

    $article = NewsArticle::factory()->published()->create([
        'featured_image_url' => null,
        'slug_pt_pt' => null,
        'slug_pt_br' => 'pt-br-slug',
        'slug_en' => 'en-slug',
    ]);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtBr,
        'title' => 'Título BR',
    ]);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::En,
        'title' => 'Title EN',
    ]);

    BroadcastNewsArticleJob::dispatchSync($article->id);

    Http::assertSent(fn ($req) => str_contains($req['text'], 'BR'));
});
