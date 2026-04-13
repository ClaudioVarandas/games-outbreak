<?php

use App\Actions\News\PublishNewsArticle;
use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets status to published and stamps published_at', function () {
    $article = NewsArticle::factory()->create(['status' => NewsArticleStatusEnum::Review]);

    (new PublishNewsArticle)->handle($article);

    $article->refresh();
    expect($article->status)->toBe(NewsArticleStatusEnum::Published);
    expect($article->published_at)->not->toBeNull();
});

it('preserves existing published_at if already set', function () {
    $past = now()->subDay();
    $article = NewsArticle::factory()->create([
        'status' => NewsArticleStatusEnum::Review,
        'published_at' => $past,
    ]);

    (new PublishNewsArticle)->handle($article);

    expect($article->fresh()->published_at->toDateString())->toBe($past->toDateString());
});
