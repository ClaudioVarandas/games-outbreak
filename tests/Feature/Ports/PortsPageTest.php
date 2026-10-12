<?php

use App\Models\PcPort;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the ports page with sections, metrics and filter options', function () {
    PcPort::factory()->released()->create(['game_title' => 'Banjo-Kazooie', 'original_platform' => 'Nintendo 64', 'last_checked_at' => '2026-10-11']);
    PcPort::factory()->create(['game_title' => 'Syphon Filter 2', 'original_platform' => 'PlayStation / PS1', 'last_checked_at' => '2026-09-01']);
    PcPort::factory()->radar()->create(['game_title' => 'Zelda Radar', 'original_platform' => 'GameCube, Wii', 'last_checked_at' => '2026-09-01']);
    PcPort::factory()->tool()->create(['game_title' => 'Multi Launcher', 'last_checked_at' => null]);

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
        ->assertSee('<option value="released">Released</option>', false)
        ->assertDontSee('<option value="beta">', false);

    $stats = collect($response->viewData('stats'))->mapWithKeys(fn (array $stat) => [$stat['metric']->value => $stat['value']]);
    expect($stats->all())->toBe(['entries' => 4, 'native' => 2, 'playable' => 1, 'platforms' => 4]);
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
