<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\NewsArticle;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
});

function sitemapLocs(string $xml): array
{
    return collect((array) simplexml_load_string($xml)->children())
        ->flatten()
        ->map(fn ($node) => (string) $node->loc)
        ->all();
}

it('serves a sitemap index pointing at every child sitemap as XML', function () {
    Game::factory()->count(3)->create();

    $response = $this->get(route('sitemap'))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    expect($response->getContent())->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and(sitemapLocs($response->getContent()))->toBe([
            route('sitemaps.pages'),
            route('sitemaps.lists'),
            route('sitemaps.news'),
            route('sitemaps.games', 1),
        ]);
});

it('lists the public pages including ports and the release years', function () {
    GameList::factory()->yearly()->system()->create(['start_at' => '2025-01-01', 'is_active' => true]);
    GameList::factory()->yearly()->system()->create(['start_at' => now()->startOfYear(), 'is_active' => true]);

    $locs = sitemapLocs($this->get(route('sitemaps.pages'))->assertSuccessful()->getContent());

    expect($locs)->toContain(route('homepage'), route('ports'), route('events'), route('videos.index'), route('releases.year', 2025))
        ->toContain(route('releases.year.month', [now()->year, now()->month]))
        ->not->toContain(route('releases.year', now()->year))
        ->toContain(route('news-articles.en.index'));
});

it('only lists system lists a guest can open', function () {
    $visible = GameList::factory()->events()->system()->public()->active()->create();
    GameList::factory()->events()->system()->create(['is_public' => false]);
    GameList::factory()->seasoned()->system()->public()->create(['is_active' => false]);
    GameList::factory()->public()->active()->create();

    $locs = sitemapLocs($this->get(route('sitemaps.lists'))->assertSuccessful()->getContent());

    expect($locs)->toBe([route('lists.show', ['type' => 'events', 'slug' => $visible->slug])]);
});

it('lists published news in every locale with hreflang alternates', function () {
    $article = NewsArticle::factory()->published()->create();
    NewsArticle::factory()->create();

    $xml = $this->get(route('sitemaps.news'))->assertSuccessful()->getContent();

    expect(sitemapLocs($xml))->toHaveCount(3)
        ->and($xml)->toContain('hreflang="pt-PT" href="'.route('news-articles.show', ['pt-pt', $article->slug_pt_pt]).'"')
        ->and($xml)->toContain('hreflang="en" href="'.route('news-articles.en.show', $article->slug_en).'"');
});

it('leaves news out entirely when the feature is not public', function () {
    config(['features.news' => 'admin']);
    NewsArticle::factory()->published()->create();

    expect(sitemapLocs($this->get(route('sitemap'))->getContent()))->not->toContain(route('sitemaps.news'))
        ->and(sitemapLocs($this->get(route('sitemaps.pages'))->getContent()))->not->toContain(route('news-articles.en.index'))
        ->and(sitemapLocs($this->get(route('sitemaps.news'))->getContent()))->toBe([]);
});

it('splits games across numbered sitemaps and 404s past the last one', function () {
    $games = Game::factory()->count(3)->create();

    $locs = sitemapLocs($this->get(route('sitemaps.games', 1))->assertSuccessful()->getContent());

    expect($locs)->toBe($games->sortBy('id')->map(fn (Game $game) => route('game.show', $game->slug))->values()->all());
    $this->get(route('sitemaps.games', 2))->assertNotFound();
    $this->get('/sitemaps/games-0.xml')->assertNotFound();
    expect(SitemapService::GAMES_PER_SITEMAP)->toBe(5000);
});
