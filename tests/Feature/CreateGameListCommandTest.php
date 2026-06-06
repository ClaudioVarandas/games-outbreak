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
