<?php

use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
});

it('shows published articles at pt-pt locale index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-anunciado']);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'Jogo Anunciado',
    ]);

    $this->get('/pt-pt/noticias')->assertOk()->assertSee('Jogo Anunciado');
});

it('shows published articles at pt-br locale index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-anunciado-pt', 'slug_pt_br' => 'jogo-anunciado-br']);
    NewsArticleLocalization::factory()->ptBr()->create([
        'news_article_id' => $article->id,
        'title' => 'Jogo Anunciado BR',
    ]);

    $this->get('/pt-br/noticias')->assertOk()->assertSee('Jogo Anunciado BR');
});

it('shows a specific article at its pt-pt slug', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-test-slug']);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'Jogo Test',
    ]);

    $this->get('/pt-pt/noticias/jogo-test-slug')->assertOk()->assertSee('Jogo Test');
});

it('shows a specific article at its pt-br slug', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-test-pt', 'slug_pt_br' => 'jogo-test-br']);
    NewsArticleLocalization::factory()->ptBr()->create([
        'news_article_id' => $article->id,
        'title' => 'Jogo Test BR',
    ]);

    $this->get('/pt-br/noticias/jogo-test-br')->assertOk()->assertSee('Jogo Test BR');
});

it('returns 404 for unpublished articles', function () {
    $article = NewsArticle::factory()->create([
        'status' => NewsArticleStatusEnum::Review,
        'slug_pt_pt' => 'draft-article',
    ]);

    $this->get('/pt-pt/noticias/draft-article')->assertNotFound();
});

it('returns 404 for unknown locale prefix', function () {
    $this->get('/fr-fr/noticias')->assertNotFound();
});
