<?php

use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
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
            ['url' => 'https://www.youtube.com/@SummerGameFest', 'network_type' => ['name' => 'YouTube']],
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
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => 'https://www.youtube.com/watch?v=CURATED', 'video_url_manual' => true]);

    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])->assertSuccessful();

    expect($list->games()->where('games.id', $game->id)->first()->pivot->video_url)
        ->toBe('https://www.youtube.com/watch?v=CURATED');
});

it('flags a year-only game as TBA with the year on import', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            return Http::response([igdbEventPayload(137, [403885])], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([array_replace(igdbGamePayload(403885), [
                'first_release_date' => 1830211200, // ~2027-12-31, the misleading concrete date
                'release_dates' => [[
                    'id' => 923495, 'date' => 1830211200, 'human' => '2027',
                    'm' => 12, 'y' => 2027, 'date_format' => 2, 'platform' => 6, 'status' => 6, // YYYY → year only
                ]],
            ])], 200);
        }

        return Http::response([], 200);
    });

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])->assertSuccessful();

    $pivot = GameList::events()->where('igdb_event_id', 137)->first()
        ->games()->where('games.igdb_id', 403885)->first()->pivot;

    expect((bool) $pivot->is_tba)->toBeTrue()
        ->and((int) $pivot->release_year)->toBe(2027)
        ->and($pivot->release_date)->toBeNull();
});

it('sets a concrete release date for a fully-dated game on import', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            return Http::response([igdbEventPayload(137, [555])], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([array_replace(igdbGamePayload(555), [
                'release_dates' => [[
                    'id' => 1, 'date' => 1773532800, 'human' => 'Mar 15, 2026',
                    'm' => 3, 'y' => 2026, 'date_format' => 0, 'platform' => 6, 'status' => 6, // YYYYMMDD → full date
                ]],
            ])], 200);
        }

        return Http::response([], 200);
    });

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])->assertSuccessful();

    $pivot = GameList::events()->where('igdb_event_id', 137)->first()
        ->games()->where('games.igdb_id', 555)->first()->pivot;

    expect((bool) $pivot->is_tba)->toBeFalse()
        ->and($pivot->release_year)->toBeNull()
        ->and($pivot->release_date)->not->toBeNull()
        ->and(Carbon::parse($pivot->release_date)->year)->toBe(2026);
});

it('refreshes a stale already-known game from IGDB during sync', function () {
    $list = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'slug' => 'summer-game-fest',
    ]);
    $game = Game::factory()->create([
        'igdb_id' => 111,
        'first_release_date' => Carbon::create(2030, 1, 1),
        'last_igdb_sync_at' => now()->subDays(10),
    ]);
    $list->games()->attach($game->id, ['order' => 1]);

    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])
        ->expectsOutputToContain('refreshed 1')
        ->assertSuccessful();

    // Tier-1 game data is refreshed from IGDB (igdbGamePayload first_release_date 1749600000 → 2025).
    expect($game->fresh()->first_release_date->year)->toBe(2025);
});

it('does not refresh a recently-synced game', function () {
    $list = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'slug' => 'summer-game-fest',
    ]);
    $game = Game::factory()->create([
        'igdb_id' => 111,
        'first_release_date' => Carbon::create(2030, 1, 1),
        'last_igdb_sync_at' => now()->subHour(),
    ]);
    $list->games()->attach($game->id, ['order' => 1]);

    fakeIgdb(igdbEventPayload(137, [111]));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])
        ->expectsOutputToContain('refreshed 0')
        ->assertSuccessful();

    expect($game->fresh()->first_release_date->year)->toBe(2030); // untouched
});

it('prefills the youtube channel url from the YouTube social link on create', function () {
    fakeIgdb(igdbEventPayload(137, []));

    $this->artisan('igdb:events:import', ['event' => '137', '--accept-all' => true])->assertSuccessful();

    $list = GameList::events()->where('igdb_event_id', 137)->first();
    expect($list->event_data['youtube_channel_url'])->toBe('https://www.youtube.com/@SummerGameFest/videos');
});

it('does not overwrite an admin-set youtube channel url on update', function () {
    $list = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'slug' => 'summer-game-fest',
        'event_data' => ['youtube_channel_url' => 'https://www.youtube.com/@Custom/videos'],
    ]);

    fakeIgdb(igdbEventPayload(137, []));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])->assertSuccessful();

    expect($list->refresh()->event_data['youtube_channel_url'])->toBe('https://www.youtube.com/@Custom/videos');
});

it('matches channel trailers on a manual --update', function () {
    config(['services.youtube.api_key' => 'test-key']);

    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            // start_time 2026-06-06 18:00 UTC, one hour before the channel reveal below.
            return Http::response([igdbEventPayload(137, [111], ['start_time' => 1780768800])], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([igdbGamePayload(111)], 200);
        }
        if (str_contains($url, 'youtube/v3/channels')) {
            return Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU']]]]], 200);
        }
        if (str_contains($url, 'youtube/v3/playlistItems')) {
            return Http::response(['items' => [
                ['snippet' => ['title' => 'Game 111 Official Trailer', 'publishedAt' => '2026-06-06T19:00:00Z', 'resourceId' => ['videoId' => 'chanVid']]],
            ]], 200);
        }

        return Http::response([], 200);
    });

    $list = GameList::factory()->events()->system()->create(['igdb_event_id' => 137, 'slug' => 'summer-game-fest']);

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])
        ->expectsOutputToContain('channel 1')
        ->assertSuccessful();

    // Channel match wins over the IGDB trailer (tr111).
    expect($list->games()->where('games.igdb_id', 111)->first()->pivot->video_url)
        ->toBe('https://www.youtube.com/watch?v=chanVid');
});

it('leaves an existing list description untouched on update', function () {
    $list = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'slug' => 'summer-game-fest',
        'description' => 'Hand-written description',
    ]);

    fakeIgdb(igdbEventPayload(137, []));

    $this->artisan('igdb:events:import', ['event' => '137', '--update' => true])->assertSuccessful();

    $list->refresh();
    expect($list->description)->toBe('Hand-written description')
        ->and($list->getEventAbout())->toBe('The big showcase.');
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
