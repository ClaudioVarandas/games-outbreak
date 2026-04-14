<?php

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
});

// ============================================================
// Middleware — locale detection and app locale setting
// ============================================================

it('sets currentNewsLocale to En on /en/news', function () {
    NewsArticle::factory()->published()->create(['slug_en' => 'test']);
    NewsArticleLocalization::factory()->for(NewsArticle::first(), 'article')->create([
        'locale' => NewsLocaleEnum::En,
    ]);

    $this->get('/en/news')
        ->assertOk()
        ->assertViewHas('currentNewsLocale', NewsLocaleEnum::En);
});

it('sets currentNewsLocale to PtPt on /pt-pt/noticias', function () {
    NewsArticle::factory()->published()->create(['slug_pt_pt' => 'teste']);
    NewsArticleLocalization::factory()->for(NewsArticle::first(), 'article')->create([
        'locale' => NewsLocaleEnum::PtPt,
    ]);

    $this->get('/pt-pt/noticias')
        ->assertOk()
        ->assertViewHas('currentNewsLocale', NewsLocaleEnum::PtPt);
});

it('sets currentNewsLocale to PtBr on /pt-br/noticias', function () {
    NewsArticle::factory()->published()->create(['slug_pt_br' => 'teste-br', 'slug_pt_pt' => 'teste-pt']);
    NewsArticleLocalization::factory()->for(NewsArticle::first(), 'article')->ptBr()->create();

    $this->get('/pt-br/noticias')
        ->assertOk()
        ->assertViewHas('currentNewsLocale', NewsLocaleEnum::PtBr);
});

// ============================================================
// Session persistence
// ============================================================

it('persists en locale to session', function () {
    NewsArticle::factory()->published()->create(['slug_en' => 'test']);
    NewsArticleLocalization::factory()->for(NewsArticle::first(), 'article')->create([
        'locale' => NewsLocaleEnum::En,
    ]);

    $this->get('/en/news')->assertSessionHas('news_locale', 'en');
});

it('persists pt-pt locale to session', function () {
    NewsArticle::factory()->published()->create(['slug_pt_pt' => 'teste']);
    NewsArticleLocalization::factory()->for(NewsArticle::first(), 'article')->create([
        'locale' => NewsLocaleEnum::PtPt,
    ]);

    $this->get('/pt-pt/noticias')->assertSessionHas('news_locale', 'pt-pt');
});

it('persists pt-br locale to session', function () {
    NewsArticle::factory()->published()->create(['slug_pt_br' => 'teste-br', 'slug_pt_pt' => 'teste-pt']);
    NewsArticleLocalization::factory()->for(NewsArticle::first(), 'article')->ptBr()->create();

    $this->get('/pt-br/noticias')->assertSessionHas('news_locale', 'pt-br');
});

// ============================================================
// fromBrowserLocale — fallback cases requiring Laravel app
// ============================================================

it('fromBrowserLocale returns app default for null header', function () {
    expect(NewsLocaleEnum::fromBrowserLocale(null))
        ->toBe(NewsLocaleEnum::fromAppLocale());
});

it('fromBrowserLocale returns app default for unsupported language', function () {
    expect(NewsLocaleEnum::fromBrowserLocale('fr-FR,fr;q=0.9'))
        ->toBe(NewsLocaleEnum::fromAppLocale());
});

// ============================================================
// /news redirect — session takes priority over browser
// ============================================================

it('redirects /news to saved session locale regardless of Accept-Language header', function () {
    $this->withSession(['news_locale' => 'pt-pt'])
        ->get('/news', ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertRedirect('/pt-pt/noticias');
});

it('redirects /news to saved en session locale even with pt-BR header', function () {
    $this->withSession(['news_locale' => 'en'])
        ->get('/news', ['Accept-Language' => 'pt-BR,pt;q=0.9'])
        ->assertRedirect('/en/news');
});

// ============================================================
// /news redirect — browser locale detection when no session
// ============================================================

it('redirects /news to en based on en-US Accept-Language', function () {
    $this->get('/news', ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertRedirect('/en/news');
});

it('redirects /news to pt-pt based on pt-PT Accept-Language', function () {
    $this->get('/news', ['Accept-Language' => 'pt-PT,pt;q=0.9'])
        ->assertRedirect('/pt-pt/noticias');
});

it('redirects /news to pt-br based on pt-BR Accept-Language', function () {
    $this->get('/news', ['Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8'])
        ->assertRedirect('/pt-br/noticias');
});

it('redirects /news to app default locale for unsupported Accept-Language', function () {
    $defaultUrl = NewsLocaleEnum::fromAppLocale()->indexUrl();

    $this->get('/news', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->assertRedirect($defaultUrl);
});

it('redirects /news to app default locale when no Accept-Language header', function () {
    $defaultUrl = NewsLocaleEnum::fromAppLocale()->indexUrl();

    $this->get('/news')->assertRedirect($defaultUrl);
});

// ============================================================
// Article show — header locale switcher
// ============================================================

it('renders header locale switcher labels on show page', function () {
    $article = NewsArticle::factory()->published()->create([
        'slug_en' => 'game-story',
        'slug_pt_pt' => 'historia-jogo',
        'slug_pt_br' => 'historia-jogo-br',
    ]);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::En,
        'title' => 'Game Story',
    ]);

    $this->get('/en/news/game-story')
        ->assertOk()
        ->assertSee('EN')
        ->assertSee('PT')
        ->assertSee('BR');
});
