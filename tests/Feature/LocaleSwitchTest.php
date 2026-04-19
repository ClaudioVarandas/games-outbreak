<?php

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets locale session and redirects on valid prefix', function () {
    $this->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect()
        ->assertSessionHas('locale', 'pt-pt');
});

it('sets locale to en', function () {
    $this->get(route('locale.switch', 'en'))
        ->assertRedirect()
        ->assertSessionHas('locale', 'en');
});

it('sets locale to pt-br', function () {
    $this->get(route('locale.switch', 'pt-br'))
        ->assertRedirect()
        ->assertSessionHas('locale', 'pt-br');
});

it('returns 404 for invalid prefix', function () {
    $this->get('/locale/fr')->assertNotFound();
});

it('redirects back to previous page', function () {
    $this->from(route('homepage'))
        ->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect(route('homepage'));
});

it('falls back to homepage when no referrer', function () {
    $this->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect(route('homepage'));
});

it('redirects to target news index when coming from a news page', function () {
    $this->from('http://localhost/en/news')
        ->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect(NewsLocaleEnum::PtPt->indexUrl());
});

it('redirects to target news index when coming from a news article', function () {
    $this->from('http://localhost/pt-br/noticias/some-slug')
        ->get(route('locale.switch', 'en'))
        ->assertRedirect(NewsLocaleEnum::En->indexUrl());
});

it('redirects to the same article in the target locale when slugs exist in both', function () {
    $article = NewsArticle::factory()->published()->create([
        'slug_en' => 'game-alpha-en',
        'slug_pt_pt' => 'jogo-alpha-pt',
    ]);

    $this->from(NewsLocaleEnum::En->articleUrl($article))
        ->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect(NewsLocaleEnum::PtPt->articleUrl($article));
});

it('falls back to target locale index when the article has no slug in that locale', function () {
    $article = NewsArticle::factory()->published()->create([
        'slug_en' => 'game-beta-en',
    ]);
    $previousUrl = NewsLocaleEnum::En->articleUrl($article);
    $article->updateQuietly(['slug_pt_pt' => null]);

    $this->from($previousUrl)
        ->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect(NewsLocaleEnum::PtPt->indexUrl());
});
