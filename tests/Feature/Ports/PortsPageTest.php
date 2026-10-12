<?php

use App\Models\PcPort;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the ports page with sections, metrics and filter options', function () {
    PcPort::factory()->released()->create(['game_title' => 'Banjo-Kazooie', 'original_platform' => 'Nintendo 64', 'last_checked_at' => '2026-10-11']);
    PcPort::factory()->create(['game_title' => 'Syphon Filter 2', 'original_platform' => 'PlayStation / PS1', 'last_checked_at' => '2026-09-01']);
    PcPort::factory()->radar()->create(['game_title' => 'Zelda Radar', 'original_platform' => 'GameCube, Wii', 'last_checked_at' => '2026-09-01']);
    PcPort::factory()->tool()->create(['game_title' => 'Multi Launcher', 'last_checked_at' => null]);
    PcPort::factory()->mod()->released()->create(['game_title' => 'Texture Mod', 'original_platform' => 'Nintendo 64', 'last_checked_at' => null]);
    PcPort::factory()->create(['game_title' => 'Notes Say Playable', 'original_platform' => 'Nintendo 64', 'details' => ['playability' => 'completable'], 'last_checked_at' => null]);

    $response = $this->get(route('ports'));

    $response->assertSuccessful()
        ->assertViewIs('ports.index')
        ->assertSeeText('Unofficial')
        ->assertSeeText('Checked Oct 11, 2026')
        ->assertSeeText('Multi & tools')
        ->assertSeeText('Mods & enhancements')
        ->assertSeeText('Radar')
        ->assertSee('Banjo-Kazooie')
        ->assertSee('<option value="PlayStation">PlayStation</option>', false)
        ->assertSee('<option value="Wii">Wii</option>', false)
        ->assertSee('<option value="playable">Playable</option>', false)
        ->assertSee('<option value="released">Released</option>', false)
        ->assertDontSee('<option value="beta">', false);

    $stats = collect($response->viewData('stats'))->mapWithKeys(fn (array $stat) => [$stat['metric']->value => $stat['value']]);
    expect($stats->all())->toBe(['entries' => 6, 'native' => 3, 'playable' => 2, 'platforms' => 4]);

    // Same definition as the Playable status filter: green statuses only, notes are ignored.
    $playable = collect($response->viewData('ports'))->where('playable', true)->pluck('title')->sort()->values()->all();
    expect($playable)->toBe(['Banjo-Kazooie', 'Texture Mod']);
});

it('passes cover urls and the typographic fallback data to the tracker', function () {
    PcPort::factory()->create(['slug' => 'with-cover', 'cover_path' => 'ports/with-cover.jpg']);
    PcPort::factory()->create(['slug' => 'no-cover', 'cover_path' => null]);

    $ports = collect($this->get(route('ports'))->viewData('ports'))->keyBy('slug');

    expect($ports['with-cover']['cover'])->toEndWith('/storage/ports/with-cover.jpg')
        ->and($ports['no-cover']['cover'])->toBeNull()
        ->and($ports['no-cover']['title'])->not->toBeEmpty();
});

it('renders an empty tracker without ports', function () {
    $this->get(route('ports'))
        ->assertSuccessful()
        ->assertSeeText('No ports found');
});

it('translates the page for portuguese visitors', function () {
    PcPort::factory()->create();

    $this->withSession(['locale' => 'pt-pt'])
        ->get(route('ports'))
        ->assertSuccessful()
        ->assertSeeText('Todas as plataformas');
});

it('shows Releases and Ports in the header navigation', function () {
    $response = $this->get(route('ports'));

    $response->assertSeeText('Releases')
        ->assertDontSeeText('Curated Lists')
        ->assertSee('href="'.route('ports').'"', false)
        ->assertSee('href="'.route('releases.year', ['year' => now()->year]).'"', false);
});

it('credits SteamGridDB in the footer', function () {
    $this->get(route('ports'))
        ->assertSuccessful()
        ->assertSee('href="https://www.steamgriddb.com/"', false)
        ->assertSeeText('SteamGridDB');
});

it('emits canonical, Open Graph and Twitter tags with the ports card image', function () {
    $image = asset('images/ports-og.jpg');

    $this->get(route('ports'))
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.route('ports').'">', false)
        ->assertSee('<meta name="description" content="Community static recompilations, decompilations and reimplementations of console games for PC.">', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta property="og:url" content="'.route('ports').'">', false)
        ->assertSee('<meta property="og:image" content="'.$image.'">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:height" content="630">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:image" content="'.$image.'">', false);

    expect(public_path('images/ports-og.jpg'))->toBeFile();
});

it('describes every port in the JSON-LD collection', function () {
    PcPort::factory()->create(['game_title' => 'Banjo-Kazooie', 'project_name' => 'BanjoRecomp', 'repo_url' => 'https://github.com/example/banjo', 'last_checked_at' => '2026-10-11']);
    PcPort::factory()->create(['game_title' => 'Zelda <Radar>', 'project_name' => null, 'repo_url' => null, 'primary_source' => 'https://example.com/zelda', 'last_checked_at' => '2026-10-01']);

    $html = $this->get(route('ports'))->assertSuccessful()->getContent();

    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match);
    $graph = collect(json_decode($match[1], true)['@graph'])->keyBy('@type');
    $list = $graph['CollectionPage']['mainEntity'];

    expect($graph['BreadcrumbList']['itemListElement'][1]['item'])->toBe(route('ports'))
        ->and($graph['CollectionPage']['dateModified'])->toBe('2026-10-11')
        ->and($list['numberOfItems'])->toBe(2)
        ->and($list['itemListElement'][0])->toMatchArray(['position' => 1, 'name' => 'Banjo-Kazooie — BanjoRecomp', 'url' => 'https://github.com/example/banjo'])
        ->and($list['itemListElement'][1])->toMatchArray(['position' => 2, 'name' => 'Zelda <Radar>', 'url' => 'https://example.com/zelda'])
        ->and($match[1])->not->toContain('<Radar>');
});
