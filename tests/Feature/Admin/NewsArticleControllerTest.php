<?php

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
});

it('admin can view articles list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    NewsArticle::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.news-articles.index'))
        ->assertOk();
});

it('admin can edit an article', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.news-articles.edit', $article))
        ->assertOk();
});

it('admin can update article localizations', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $payload = [
        'localizations' => [
            [
                'locale' => 'pt-PT',
                'title' => 'Título PT',
                'summary_short' => 'Resumo curto.',
                'summary_medium' => 'Resumo médio.',
            ],
            [
                'locale' => 'pt-BR',
                'title' => 'Título BR',
                'summary_short' => 'Resumo curto.',
                'summary_medium' => 'Resumo médio.',
            ],
        ],
    ];

    $this->actingAs($admin)
        ->patch(route('admin.news-articles.update', $article), $payload)
        ->assertRedirect(route('admin.news-articles.edit', $article));

    $this->assertDatabaseHas('news_article_localizations', [
        'news_article_id' => $article->id,
        'locale' => 'pt-PT',
        'title' => 'Título PT',
    ]);
});

it('admin can publish an article', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create(['status' => NewsArticleStatusEnum::Review]);

    $this->actingAs($admin)
        ->post(route('admin.news-articles.publish', $article))
        ->assertRedirect(route('admin.news-articles.edit', $article));

    expect($article->fresh()->status)->toBe(NewsArticleStatusEnum::Published);
});

it('admin can delete an article', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.news-articles.destroy', $article))
        ->assertRedirect(route('admin.news-articles.index'));

    $this->assertModelMissing($article);
});

it('forbids non-admin users from accessing article index', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.news-articles.index'))
        ->assertForbidden();
});
