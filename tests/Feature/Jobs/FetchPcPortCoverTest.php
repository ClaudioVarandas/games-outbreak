<?php

use App\Jobs\FetchPcPortCover;
use App\Models\PcPort;
use App\Services\IgdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('downloads the curated SteamGridDB cover into the ports directory', function () {
    Http::fake(['cdn2.steamgriddb.com/*' => Http::response('image-bytes')]);
    $port = PcPort::factory()->create(['slug' => 'banjo-kazooie', 'cover_source_url' => 'https://cdn2.steamgriddb.com/grid/abc.png']);

    (new FetchPcPortCover($port->id))->handle(app(IgdbService::class));

    expect($port->fresh()->cover_path)->toBe('ports/banjo-kazooie.png');
    Storage::disk('public')->assertExists('ports/banjo-kazooie.png');
});

it('replaces the previous cover file when the source changes', function () {
    Http::fake(['cdn2.steamgriddb.com/*' => Http::response('new-bytes')]);
    Storage::disk('public')->put('ports/banjo-kazooie.png', 'old-bytes');
    $port = PcPort::factory()->create([
        'slug' => 'banjo-kazooie',
        'cover_source_url' => 'https://cdn2.steamgriddb.com/grid/new.webp',
        'cover_path' => 'ports/banjo-kazooie.png',
    ]);

    (new FetchPcPortCover($port->id))->handle(app(IgdbService::class));

    expect($port->fresh()->cover_path)->toBe('ports/banjo-kazooie.webp');
    Storage::disk('public')->assertMissing('ports/banjo-kazooie.png');
});

it('refuses covers that are not hosted on SteamGridDB', function () {
    Http::fake();
    $port = PcPort::factory()->create(['cover_source_url' => 'https://example.com/cover.png']);

    (new FetchPcPortCover($port->id))->handle(app(IgdbService::class));

    expect($port->fresh()->cover_path)->toBeNull();
    Http::assertNothingSent();
});

it('keeps the cover empty when the download fails', function () {
    Http::fake(['cdn2.steamgriddb.com/*' => Http::response('', 404)]);
    $port = PcPort::factory()->create(['cover_source_url' => 'https://cdn2.steamgriddb.com/grid/abc.png']);

    (new FetchPcPortCover($port->id))->handle(app(IgdbService::class));

    expect($port->fresh()->cover_path)->toBeNull();
});

it('falls back to a SteamGridDB title search without a curated url', function () {
    Http::fake(['cdn2.steamgriddb.com/*' => Http::response('found-bytes')]);
    $port = PcPort::factory()->create(['slug' => 'dantes-inferno', 'game_title' => 'Dante\'s Inferno', 'cover_source_url' => null]);
    $igdb = $this->mock(IgdbService::class);
    $igdb->shouldReceive('findSteamGridDbImageUrl')->once()->with('Dante\'s Inferno', 'cover')->andReturn('https://cdn2.steamgriddb.com/grid/found.png');

    (new FetchPcPortCover($port->id))->handle($igdb);

    expect($port->fresh()->cover_path)->toBe('ports/dantes-inferno.png');
    Storage::disk('public')->assertExists('ports/dantes-inferno.png');
});

it('keeps the typographic cover for multi-game tools without a curated url', function () {
    $port = PcPort::factory()->tool()->create(['cover_source_url' => null]);
    $igdb = $this->mock(IgdbService::class);
    $igdb->shouldNotReceive('findSteamGridDbImageUrl');

    (new FetchPcPortCover($port->id))->handle($igdb);

    expect($port->fresh()->cover_path)->toBeNull();
});
