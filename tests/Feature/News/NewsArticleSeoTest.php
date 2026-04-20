<?php

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
});

it('emits canonical, hreflang, OG and breadcrumb JSON-LD on the en index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_en' => 'hello-world']);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::En,
        'title' => 'Hello World',
    ]);

    $response = $this->get('/en/news')->assertOk();

    $html = $response->getContent();

    $response->assertSee('<link rel="canonical" href="'.NewsLocaleEnum::En->indexUrl().'"', false);
    $response->assertSee('hreflang="en"', false);
    $response->assertSee('hreflang="pt-PT"', false);
    $response->assertSee('hreflang="pt-BR"', false);
    $response->assertSee('hreflang="x-default"', false);
    $response->assertSee('<meta property="og:type" content="website">', false);
    $response->assertSee('<meta property="og:locale" content="en"', false);
    $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    $response->assertSee('<html lang="en"', false);
    $response->assertSee('"@type":"BreadcrumbList"', false);
});

it('emits NewsArticle JSON-LD and article OG tags on a published detail page', function () {
    $article = NewsArticle::factory()->published()->create([
        'slug_en' => 'big-reveal',
        'slug_pt_pt' => 'grande-revelacao-pt',
        'slug_pt_br' => 'grande-revelacao-br',
        'featured_image_url' => 'https://example.test/cover.jpg',
        'source_name' => 'Insider',
        'source_url' => 'https://insider.test/article',
    ]);
    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::En,
        'title' => 'Big Reveal',
        'summary_short' => 'A surprising industry reveal.',
        'seo_title' => 'Big Reveal - SEO',
        'seo_description' => 'Concise SEO description.',
    ]);

    $response = $this->get('/en/news/big-reveal')->assertOk();

    $response->assertSee('<link rel="canonical" href="'.NewsLocaleEnum::En->articleUrl($article).'"', false);
    $response->assertSee('<meta name="description" content="Concise SEO description."', false);
    $response->assertSee('<meta property="og:type" content="article">', false);
    $response->assertSee('<meta property="og:image" content="https://example.test/cover.jpg">', false);
    $response->assertSee('<meta property="article:published_time"', false);
    $response->assertSee('"@type":"NewsArticle"', false);
    $response->assertSee('"inLanguage":"en"', false);
    $response->assertSee('"@type":"BreadcrumbList"', false);
    $response->assertSee('<html lang="en"', false);
});

it('emits only available hreflang variants when other-locale slugs are missing', function () {
    $article = NewsArticle::factory()->published()->create([
        'slug_en' => 'only-en',
    ]);
    // Null out the other-locale slugs set by the creating hook.
    $article->updateQuietly(['slug_pt_pt' => null, 'slug_pt_br' => null]);

    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::En,
        'title' => 'Only EN',
    ]);

    $response = $this->get('/en/news/only-en')->assertOk();

    $response->assertSee('hreflang="en"', false);
    $response->assertDontSee('hreflang="pt-PT"', false);
    $response->assertDontSee('hreflang="pt-BR"', false);
});
