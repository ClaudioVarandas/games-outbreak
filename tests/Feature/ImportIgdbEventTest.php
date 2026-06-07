<?php

use App\Models\Game;
use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function igdbEventPayload(int $id, array $gameIds, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'name' => 'Summer Game Fest',
        'description' => 'The big showcase.',
        'slug' => 'igdb-owned-slug',
        'start_time' => 1749500000,
        'end_time' => 1749510000,
        'time_zone' => 'America/Los_Angeles',
        'live_stream_url' => 'https://www.youtube.com/watch?v=ABC123',
        'event_networks' => [
            ['url' => 'https://twitter.com/sgf', 'network_type' => ['name' => 'Twitter']],
        ],
        'games' => $gameIds,
        'videos' => [['video_id' => 'XYZ789']],
    ], $overrides);
}

function igdbGamePayload(int $id): array
{
    return [
        'id' => $id,
        'name' => "Game {$id}",
        'summary' => 'A game.',
        'first_release_date' => 1749600000,
        'cover' => ['image_id' => 'co'.$id],
        'platforms' => [['id' => 6, 'name' => 'PC']],
        'genres' => [],
        'game_modes' => [],
        'external_games' => [],
        'websites' => [],
        'game_type' => 0,
        'release_dates' => null,
        'videos' => [['video_id' => 'tr'.$id]],
    ];
}

/**
 * Fake IGDB so /v4/events returns $event and /v4/games returns the single game
 * whose id is in the request body (`where id = N`). Pass a closure for $events
 * to vary the event response between calls (re-run scenarios).
 */
function fakeIgdb(array|Closure $event, array $knownGameIds = []): void
{
    Http::fake(function ($request) use ($event) {
        $url = $request->url();

        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }

        if (str_contains($url, '/v4/events')) {
            $payload = $event instanceof Closure ? $event() : $event;

            return Http::response($payload === null ? [] : [$payload], 200);
        }

        if (str_contains($url, '/v4/games')) {
            preg_match('/where id = (\d+)/', $request->body(), $m);
            $id = (int) ($m[1] ?? 0);

            return Http::response($id ? [igdbGamePayload($id)] : [], 200);
        }

        return Http::response([], 200);
    });
}

beforeEach(function () {
    Queue::fake();
});

it('creates an events list from a numeric IGDB event id', function () {
    fakeIgdb(igdbEventPayload(137, [111, 222]));

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])
        ->assertSuccessful();

    $list = GameList::events()->where('igdb_event_id', 137)->first();

    expect($list)->not->toBeNull()
        ->and($list->name)->toBe('Summer Game Fest')
        ->and($list->slug)->toBe('summer-game-fest')
        ->and($list->is_system)->toBeTrue()
        ->and($list->getEventAbout())->toBe('The big showcase.')
        ->and($list->games()->count())->toBe(2);

    $pivot = $list->games()->where('games.igdb_id', 111)->first()->pivot;
    expect(json_decode($pivot->platforms, true))->toBe([6]);
});

it('sets each new game pivot video_url from its IGDB trailer', function () {
    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])
        ->assertSuccessful();

    $pivot = GameList::events()->where('igdb_event_id', 137)->first()
        ->games()->where('games.igdb_id', 111)->first()->pivot;

    expect($pivot->video_url)->toBe('https://www.youtube.com/watch?v=tr111');
});

it('backfills an already-attached game pivot video_url when it is empty', function () {
    $list = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'slug' => 'summer-game-fest',
    ]);
    $game = Game::factory()->create(['igdb_id' => 111, 'trailers' => [['video_id' => 'tr111']]]);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => null]);

    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])
        ->assertSuccessful();

    expect($list->games()->where('games.id', $game->id)->first()->pivot->video_url)
        ->toBe('https://www.youtube.com/watch?v=tr111');
});

it('does not overwrite an admin-set pivot video_url on a re-sync', function () {
    $list = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'slug' => 'summer-game-fest',
    ]);
    $game = Game::factory()->create(['igdb_id' => 111, 'trailers' => [['video_id' => 'tr111']]]);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => 'https://www.youtube.com/watch?v=CURATED']);

    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])->assertSuccessful();

    expect($list->games()->where('games.id', $game->id)->first()->pivot->video_url)
        ->toBe('https://www.youtube.com/watch?v=CURATED');
});

it('updates an existing list and adds only the newly-appeared games', function () {
    $list = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'slug' => 'summer-game-fest',
        'name' => 'Summer Game Fest',
        'event_data' => ['about' => 'old text'],
    ]);
    $existingGame = Game::factory()->create(['igdb_id' => 111]);
    $list->games()->attach($existingGame->id, ['order' => 1, 'platforms' => json_encode([6])]);

    fakeIgdb(igdbEventPayload(137, [111, 222]));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])
        ->expectsOutputToContain('added 1')
        ->assertSuccessful();

    $list->refresh();
    expect($list->games()->count())->toBe(2)
        ->and($list->getEventAbout())->toBe('The big showcase.')
        ->and(GameList::events()->where('igdb_event_id', 137)->count())->toBe(1);
});

it('is idempotent across re-runs, picking up games added between runs', function () {
    $gameIds = [111, 222];
    fakeIgdb(function () use (&$gameIds) {
        return igdbEventPayload(137, $gameIds);
    });

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])
        ->assertSuccessful();

    expect(GameList::events()->where('igdb_event_id', 137)->first()->games()->count())->toBe(2);

    // IGDB adds a third game during the event.
    $gameIds[] = 333;

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])
        ->expectsOutputToContain('added 1')
        ->assertSuccessful();

    $list = GameList::events()->where('igdb_event_id', 137)->first();
    expect($list->games()->count())->toBe(3);
});

it('imports the event metadata only when --no-games is passed', function () {
    fakeIgdb(igdbEventPayload(137, [111, 222]));

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true, '--no-games' => true])
        ->assertSuccessful();

    $list = GameList::events()->where('igdb_event_id', 137)->first();
    expect($list)->not->toBeNull()
        ->and($list->games()->count())->toBe(0);
});

it('handles an event with an empty games array without error', function () {
    fakeIgdb(igdbEventPayload(137, []));

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])
        ->assertSuccessful();

    $list = GameList::events()->where('igdb_event_id', 137)->first();
    expect($list)->not->toBeNull()
        ->and($list->games()->count())->toBe(0);
});

it('lets the user search and select an event by name', function () {
    Queue::fake();
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            if (str_contains($request->body(), 'where id = 137')) {
                return Http::response([igdbEventPayload(137, [111])], 200);
            }

            return Http::response([
                ['id' => 137, 'name' => 'Summer Game Fest 2026', 'start_time' => 1749500000],
                ['id' => 138, 'name' => 'Summer Game Fest 2025', 'start_time' => 1717000000],
            ], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([igdbGamePayload(111)], 200);
        }

        return Http::response([], 200);
    });

    $this->artisan('igdb:events:import', ['event' => 'Summer Game Fest'])
        ->expectsQuestion('Pick the IGDB event', '137')
        ->expectsConfirmation('Create new events list for "Summer Game Fest"?', 'yes')
        ->assertSuccessful();

    expect(GameList::events()->where('igdb_event_id', 137)->exists())->toBeTrue();
});

it('auto-picks the only search match', function () {
    Queue::fake();
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            if (str_contains($request->body(), 'where id = 137')) {
                return Http::response([igdbEventPayload(137, [111])], 200);
            }

            return Http::response([['id' => 137, 'name' => 'Nacon Connect', 'start_time' => 1749500000]], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([igdbGamePayload(111)], 200);
        }

        return Http::response([], 200);
    });

    $this->artisan('igdb:events:import', ['event' => 'Nacon', '--accept-all' => true])
        ->assertSuccessful();

    expect(GameList::events()->where('igdb_event_id', 137)->exists())->toBeTrue();
});

it('fails with --create when a matching list already exists', function () {
    GameList::factory()->events()->system()->create(['igdb_event_id' => 137, 'slug' => 'summer-game-fest']);
    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--create' => true])
        ->assertFailed();
});

it('fails with --update when no matching list exists', function () {
    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])
        ->assertFailed();
});

it('does nothing when the user declines to create', function () {
    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137'])
        ->expectsConfirmation('Create new events list for "Summer Game Fest"?', 'no')
        ->assertSuccessful();

    expect(GameList::events()->where('igdb_event_id', 137)->exists())->toBeFalse();
});

it('counts a game that fails to fetch and still imports the rest', function () {
    Queue::fake();
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            return Http::response([igdbEventPayload(137, [111, 999])], 200);
        }
        if (str_contains($url, '/v4/games')) {
            if (str_contains($request->body(), 'where id = 999')) {
                return Http::response([], 404);
            }

            return Http::response([igdbGamePayload(111)], 200);
        }

        return Http::response([], 200);
    });

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])
        ->expectsOutputToContain('failed 1')
        ->assertSuccessful();

    $list = GameList::events()->where('igdb_event_id', 137)->first();
    expect($list->games()->count())->toBe(1);
});
