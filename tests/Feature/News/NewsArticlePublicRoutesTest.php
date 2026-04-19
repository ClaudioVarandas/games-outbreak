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

// ============================================================
// EN routes
// ============================================================

it('shows published articles at en locale index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_en' => 'game-announced']);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::En,
        'title' => 'Game Announced',
    ]);

    $this->get('/en/news')->assertOk()->assertSee('Game Announced');
});

it('renders summary_short on each row of the en index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_en' => 'neon-rpg']);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::En,
        'title' => 'Neon RPG',
        'summary_short' => 'Breakthrough neon RPG hits next month.',
    ]);

    $this->get('/en/news')
        ->assertOk()
        ->assertSeeText('Breakthrough neon RPG hits next month.');
});

it('shows a specific article at its en slug', function () {
    $article = NewsArticle::factory()->published()->create(['slug_en' => 'game-test-slug']);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::En,
        'title' => 'Game Test EN',
    ]);

    $this->get('/en/news/game-test-slug')->assertOk()->assertSee('Game Test EN');
});

it('returns 404 for unpublished article at en slug', function () {
    NewsArticle::factory()->create([
        'status' => NewsArticleStatusEnum::Review,
        'slug_en' => 'draft-en',
    ]);

    $this->get('/en/news/draft-en')->assertNotFound();
});

// ============================================================
// PT-PT routes
// ============================================================

it('shows published articles at pt-pt locale index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-anunciado']);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'Jogo Anunciado',
    ]);

    $this->get('/pt-pt/noticias')->assertOk()->assertSee('Jogo Anunciado');
});

it('shows a specific article at its pt-pt slug', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-test-slug']);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'Jogo Test',
    ]);

    $this->get('/pt-pt/noticias/jogo-test-slug')->assertOk()->assertSee('Jogo Test');
});

// ============================================================
// PT-BR routes
// ============================================================

it('shows published articles at pt-br locale index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-pt', 'slug_pt_br' => 'jogo-br']);
    NewsArticleLocalization::factory()->for($article, 'article')->ptBr()->create([
        'title' => 'Jogo Anunciado BR',
    ]);

    $this->get('/pt-br/noticias')->assertOk()->assertSee('Jogo Anunciado BR');
});

it('shows a specific article at its pt-br slug', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-test-pt', 'slug_pt_br' => 'jogo-test-br']);
    NewsArticleLocalization::factory()->for($article, 'article')->ptBr()->create([
        'title' => 'Jogo Test BR',
    ]);

    $this->get('/pt-br/noticias/jogo-test-br')->assertOk()->assertSee('Jogo Test BR');
});

// ============================================================
// Common failure paths
// ============================================================

it('returns 404 for unpublished articles', function () {
    NewsArticle::factory()->create([
        'status' => NewsArticleStatusEnum::Review,
        'slug_pt_pt' => 'draft-article',
    ]);

    $this->get('/pt-pt/noticias/draft-article')->assertNotFound();
});

it('returns 404 for unknown locale prefix', function () {
    $this->get('/fr-fr/noticias')->assertNotFound();
});

it('redirects /news to locale from app.locale', function () {
    $this->get('/news')->assertRedirect();
});
