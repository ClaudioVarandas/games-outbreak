<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    User::factory()->create(); // CreateGameList attributes the list to user_id 1
});

it('creates a list and fetches a missing game from IGDB', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([[
                'id' => 555,
                'name' => 'Fetched Game',
                'summary' => 'Summary',
                'first_release_date' => 1749600000,
                'cover' => ['image_id' => 'co555'],
                'platforms' => [['id' => 6, 'name' => 'PC']],
                'genres' => [],
                'game_modes' => [],
                'external_games' => [],
                'websites' => [],
                'game_type' => 0,
                'release_dates' => null,
            ]], 200);
        }

        return Http::response([], 200);
    });

    $this->artisan('games:lists:create', [
        '--name' => 'My List',
        '--start-date' => '2026-01-01',
        '--end-date' => '2026-01-31',
        '--is-active' => 'yes',
        '--is-public' => 'yes',
        '--is-system' => 'no',
        '--igdb-ids' => '555',
    ])->assertSuccessful();

    $list = GameList::where('name', 'My List')->first();

    expect($list)->not->toBeNull()
        ->and(Game::where('igdb_id', 555)->exists())->toBeTrue()
        ->and($list->games()->where('games.igdb_id', 555)->exists())->toBeTrue();
});

it('flags a year-only game as TBA with the year', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([[
                'id' => 403885,
                'name' => 'Year Only Game',
                'summary' => 'Summary',
                'first_release_date' => 1830211200, // ~2027-12-31 concrete
                'cover' => ['image_id' => 'co403885'],
                'platforms' => [['id' => 6, 'name' => 'PC']],
                'genres' => [],
                'game_modes' => [],
                'external_games' => [],
                'websites' => [],
                'game_type' => 0,
                'release_dates' => [[
                    'id' => 1, 'date' => 1830211200, 'human' => '2027',
                    'm' => 12, 'y' => 2027, 'date_format' => 2, 'platform' => 6, 'status' => 6, // YYYY → year only
                ]],
            ]], 200);
        }

        return Http::response([], 200);
    });

    $this->artisan('games:lists:create', [
        '--name' => 'TBA List',
        '--start-date' => '2027-01-01',
        '--end-date' => '2027-12-31',
        '--is-active' => 'yes',
        '--is-public' => 'yes',
        '--is-system' => 'no',
        '--igdb-ids' => '403885',
    ])->assertSuccessful();

    $pivot = GameList::where('name', 'TBA List')->first()
        ->games()->where('games.igdb_id', 403885)->first()->pivot;

    expect((bool) $pivot->is_tba)->toBeTrue()
        ->and((int) $pivot->release_year)->toBe(2027)
        ->and($pivot->release_date)->toBeNull();
});

it('reuses a game already present in the database', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/*' => Http::response([], 200),
    ]);

    $game = Game::factory()->create(['igdb_id' => 777]);

    $this->artisan('games:lists:create', [
        '--name' => 'Reuse List',
        '--start-date' => '2026-02-01',
        '--end-date' => '2026-02-28',
        '--is-active' => 'yes',
        '--is-public' => 'yes',
        '--is-system' => 'no',
        '--igdb-ids' => '777',
    ])->assertSuccessful();

    $list = GameList::where('name', 'Reuse List')->first();

    expect($list->games()->where('games.id', $game->id)->exists())->toBeTrue();
});
