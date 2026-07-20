<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeIgdbGameSearch(array $rows): void
{
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::response($rows, 200),
    ]);
}

it('prints matching IGDB games as JSON candidates', function () {
    fakeIgdbGameSearch([
        [
            'id' => 1234,
            'name' => 'Silent Hill Townfall',
            'slug' => 'silent-hill-townfall',
            'game_type' => 0,
            'first_release_date' => 1760486400, // 2025-10-15
            'summary' => 'A new entry in the series.',
            'platforms' => [
                ['id' => 6, 'name' => 'PC (Microsoft Windows)'],
                ['id' => 167, 'name' => 'PlayStation 5'],
            ],
            'release_dates' => [
                ['human' => 'Oct 15, 2025', 'y' => 2025, 'm' => 10],
            ],
            'external_games' => [
                ['external_game_source' => 1, 'uid' => '999888', 'url' => 'https://store.steampowered.com/app/999888'],
            ],
        ],
    ]);

    $exitCode = Artisan::call('games:igdb-search', ['name' => 'Silent Hill Townfall']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"igdb_id": 1234')
        ->and($output)->toContain('"first_release_date": "2025-10-15"')
        ->and($output)->toContain('"release_year": 2025')
        ->and($output)->toContain('"game_type": "Main Game"')
        ->and($output)->toContain('"steam_app_id": "999888"')
        ->and($output)->toContain('Oct 15, 2025');
});

it('falls back to word matching when the search clause returns nothing', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::sequence()
            ->push([], 200)
            ->push([['id' => 55, 'name' => 'Grand Theft Auto VI']], 200),
    ]);

    Artisan::call('games:igdb-search', ['name' => 'GTA 6']);

    expect(Artisan::output())->toContain('"igdb_id": 55');

    Http::assertSentCount(3); // token + search query + fallback query
});

it('ranks candidates matching the expected year first', function () {
    fakeIgdbGameSearch([
        ['id' => 1, 'name' => 'Remake (2020)', 'first_release_date' => 1583020800], // 2020
        ['id' => 2, 'name' => 'Remake (2026)', 'first_release_date' => 1782864000], // 2026
    ]);

    Artisan::call('games:igdb-search', ['name' => 'Remake', '--year' => 2026]);
    $output = Artisan::output();

    expect(strpos($output, '"igdb_id": 2'))->toBeLessThan(strpos($output, '"igdb_id": 1'));
});

it('returns an empty candidate list for a blank name', function () {
    Http::fake();

    Artisan::call('games:igdb-search', ['name' => '  ']);

    expect(Artisan::output())->toContain('"candidates": []');
    Http::assertNothingSent();
});
