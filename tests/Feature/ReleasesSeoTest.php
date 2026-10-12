<?php

use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->list = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2025, 1, 1),
        'end_at' => Carbon::create(2025, 12, 31),
    ]);

    $this->octoberGames = Game::factory()->count(2)->create();
    foreach ($this->octoberGames as $game) {
        $this->list->games()->attach($game->id, ['release_date' => '2025-10-15 00:00:00', 'is_tba' => false]);
    }
    $this->list->games()->attach(Game::factory()->create()->id, ['release_date' => '2025-11-03 00:00:00', 'is_tba' => false]);
});

function releasesJsonLd(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match);

    return json_decode($match[1], true);
}

it('gives monthly pages the monthly releases card and a counted description', function () {
    $image = asset('images/releases-monthly-og.jpg');
    $url = route('releases.year.month', [2025, 10]);

    $this->get($url)
        ->assertSuccessful()
        ->assertSee('<title>October Game Releases 2025 | Games Outbreak</title>', false)
        ->assertSee('<meta name="description" content="2 game releases for October 2025 — release dates, platforms and trailers, curated by Games Outbreak.">', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta property="og:url" content="'.$url.'">', false)
        ->assertSee('<meta property="og:image" content="'.$image.'">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:image" content="'.$image.'">', false);

    expect(public_path('images/releases-monthly-og.jpg'))->toBeFile();
});

it('lists the month games in the JSON-LD collection with a breadcrumb', function () {
    $graph = collect(releasesJsonLd($this->get(route('releases.year.month', [2025, 10]))->getContent())['@graph'])->keyBy('@type');

    $crumbs = collect($graph['BreadcrumbList']['itemListElement'])->pluck('item')->all();
    $items = $graph['CollectionPage']['mainEntity']['itemListElement'];

    expect($crumbs)->toBe([url('/'), route('releases.year', 2025), route('releases.year.month', [2025, 10])])
        ->and($graph['CollectionPage']['mainEntity']['numberOfItems'])->toBe(2)
        ->and(collect($items)->pluck('url')->sort()->values()->all())
        ->toBe($this->octoberGames->map(fn (Game $game) => route('game.show', $game->slug))->sort()->values()->all());
});

it('uses a generic description for a month without releases', function () {
    $this->get(route('releases.year.month', [2025, 2]))
        ->assertSuccessful()
        ->assertSee('<meta name="description" content="Game releases for February 2025 — release dates, platforms and trailers, curated by Games Outbreak.">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/releases-monthly-og.jpg').'">', false);
});

it('keeps the yearly page without the monthly card', function () {
    $response = $this->get(route('releases.year', 2025))
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.route('releases.year', 2025).'">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertDontSee('og:image', false);

    expect(releasesJsonLd($response->getContent())['@type'])->toBe('BreadcrumbList');
});

it('canonicalises the TBA view to itself', function () {
    $this->get(route('releases.year', 2025).'?only=tba')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.route('releases.year', 2025).'?only=tba">', false);
});
