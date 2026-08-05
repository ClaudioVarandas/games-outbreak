<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function releaseWindowGame(int $id, string $name, int $timestamp, int $hypes = 0): array
{
    return [
        'id' => $id,
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'game_type' => 0,
        'first_release_date' => $timestamp,
        'hypes' => $hypes,
        'platforms' => [['id' => 6, 'name' => 'PC (Microsoft Windows)']],
        'release_dates' => [['human' => 'Aug 2026']],
        'external_games' => [
            ['external_game_source' => 1, 'uid' => '111222', 'url' => 'https://store.steampowered.com/app/111222'],
        ],
    ];
}

function fakeIgdbWindow(array $rows): void
{
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::response($rows, 200),
    ]);
}

it('expands a YYYY-MM shorthand to the full month and queries that window', function () {
    fakeIgdbWindow([releaseWindowGame(1, 'August Game', mktime(0, 0, 0, 8, 10, 2026))]);

    $exitCode = Artisan::call('games:release-window', ['window' => '2026-08']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"from": "2026-08-01"')
        ->and($output)->toContain('"to": "2026-08-31"')
        ->and($output)->toContain('"igdb_id": 1');

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'api.igdb.com/v4/games')) {
            return true;
        }

        return str_contains($request->body(), 'first_release_date >=')
            && str_contains($request->body(), 'hypes')
            && str_contains($request->body(), 'limit 500');
    });
});

it('accepts an explicit from..to range', function () {
    fakeIgdbWindow([]);

    $exitCode = Artisan::call('games:release-window', ['window' => '2026-08-01..2026-08-15']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"from": "2026-08-01"')
        ->and($output)->toContain('"to": "2026-08-15"');
});

it('rejects malformed windows', function (string $window) {
    fakeIgdbWindow([]);

    expect(Artisan::call('games:release-window', ['window' => $window]))->toBe(1)
        ->and(Artisan::output())->toContain('Invalid window');
})->with([
    'garbage' => 'not-a-window',
    'reversed range' => '2026-09-01..2026-08-01',
    'bad month' => '2026-13',
    'half range' => '2026-08-01..',
]);

it('rejects windows spanning more than 92 days', function () {
    fakeIgdbWindow([]);

    expect(Artisan::call('games:release-window', ['window' => '2026-01-01..2026-06-30']))->toBe(1)
        ->and(Artisan::output())->toContain('Window too large');
});

it('ranks candidates by hypes and respects the limit', function () {
    fakeIgdbWindow([
        releaseWindowGame(1, 'Sleeper', mktime(0, 0, 0, 8, 5, 2026), hypes: 2),
        releaseWindowGame(2, 'Blockbuster', mktime(0, 0, 0, 8, 20, 2026), hypes: 90),
        releaseWindowGame(3, 'Mid Game', mktime(0, 0, 0, 8, 12, 2026), hypes: 40),
    ]);

    Artisan::call('games:release-window', ['window' => '2026-08', '--limit' => 2]);
    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['candidates'])->toHaveCount(2)
        ->and($decoded['candidates'][0]['name'])->toBe('Blockbuster')
        ->and($decoded['candidates'][0]['hypes'])->toBe(90)
        ->and($decoded['candidates'][1]['name'])->toBe('Mid Game')
        ->and($decoded['candidates'][0])->toHaveKeys(['igdb_id', 'slug', 'game_type', 'first_release_date', 'release_year', 'platforms', 'release_dates', 'steam_app_id', 'summary']);
});

it('passes the platforms filter into the IGDB query', function () {
    fakeIgdbWindow([]);

    Artisan::call('games:release-window', ['window' => '2026-08', '--platforms' => '6,167']);

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'api.igdb.com/v4/games')) {
            return true;
        }

        return str_contains($request->body(), 'platforms = (6,167)');
    });
});

it('pages through full 500-row chunks', function () {
    $fullPage = collect(range(1, 500))
        ->map(fn (int $i): array => releaseWindowGame($i, "Game {$i}", mktime(0, 0, 0, 8, 10, 2026)))
        ->all();

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::sequence()
            ->push($fullPage, 200)
            ->push([releaseWindowGame(501, 'Tail Game', mktime(0, 0, 0, 8, 25, 2026))], 200),
    ]);

    Artisan::call('games:release-window', ['window' => '2026-08', '--limit' => 600]);
    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['candidates'])->toHaveCount(501);

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'api.igdb.com/v4/games')) {
            return true;
        }

        return str_contains($request->body(), 'offset 0') || str_contains($request->body(), 'offset 500');
    });
});
