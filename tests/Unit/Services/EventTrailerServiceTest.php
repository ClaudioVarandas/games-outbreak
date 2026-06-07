<?php

use App\Models\Game;
use App\Models\GameList;
use App\Services\EventTrailerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const EVENT_START = '2026-06-06T19:00:00Z';

function channelItem(string $title, string $videoId, string $publishedAt): array
{
    return ['snippet' => ['title' => $title, 'publishedAt' => $publishedAt, 'resourceId' => ['videoId' => $videoId]]];
}

/**
 * Fake the channel uploads (single page) and the IGDB batch trailer refresh.
 *
 * @param  array<int, array<int, array<string, mixed>>>  $igdbVideosByGame  [igdbId => videos[]]
 */
function fakeTrailerSources(array $channelItems, array $igdbVideosByGame = []): void
{
    config(['services.youtube.api_key' => 'test-key']);

    Http::fake(function ($request) use ($channelItems, $igdbVideosByGame) {
        $url = $request->url();

        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/games')) {
            $body = $request->body();
            $games = [];
            foreach ($igdbVideosByGame as $igdbId => $videos) {
                if (preg_match('/\b'.$igdbId.'\b/', $body)) {
                    $games[] = ['id' => $igdbId, 'videos' => $videos];
                }
            }

            return Http::response($games, 200);
        }
        if (str_contains($url, 'youtube/v3/channels')) {
            return Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU']]]]], 200);
        }
        if (str_contains($url, 'youtube/v3/playlistItems')) {
            return Http::response(['items' => $channelItems], 200);
        }

        return Http::response([], 200);
    });
}

function trailerEvent(?string $channelUrl = 'https://www.youtube.com/@FGS/videos'): GameList
{
    return GameList::factory()->events()->system()->create([
        'start_at' => Carbon::parse(EVENT_START),
        'event_data' => $channelUrl ? ['youtube_channel_url' => $channelUrl] : [],
    ]);
}

function attachGame(GameList $list, Game $game, array $pivot = []): void
{
    $list->games()->attach($game->id, array_merge(['order' => 1], $pivot));
}

function resolveTrailers(GameList $list): array
{
    return app(EventTrailerService::class)->resolve($list);
}

it('prefers the in-show reveal over the post-show livestream VOD and the pre-event teaser', function () {
    fakeTrailerSources([
        channelItem('Future Games Show Summer Showcase 2026 - Official Livestream (Exodus, more)', 'vodId', '2026-06-06T21:36:00Z'),
        channelItem('EXODUS Extended Gameplay Reveal - Future Games Show', 'revealId', '2026-06-06T20:29:00Z'),
        channelItem('The most weve seen of EXODUS yet', 'teaserId', '2026-05-29T16:00:00Z'),
    ]);

    $list = trailerEvent();
    $game = Game::factory()->create(['name' => 'EXODUS', 'igdb_id' => 279621, 'trailers' => null]);
    attachGame($list, $game, ['video_url' => null]);

    $report = resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=revealId')
        ->and($report['channel'])->toBe(1);
});

it('matches a reveal that is beyond the first page of channel uploads', function () {
    config(['services.youtube.api_key' => 'test-key']);
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([], 200);
        }
        if (str_contains($url, 'youtube/v3/channels')) {
            return Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU']]]]], 200);
        }
        if (str_contains($url, 'pageToken=p2')) {
            return Http::response(['items' => [
                channelItem('Sky: Children Of The Light Dear Van Gogh Reveal Trailer - Future Games Show', 'skyId', '2026-06-06T19:18:00Z'),
            ]], 200);
        }

        return Http::response([
            'nextPageToken' => 'p2',
            'items' => [channelItem('Some Other Reveal - Future Games Show', 'otherId', '2026-06-06T19:49:00Z')],
        ], 200);
    });

    $list = trailerEvent();
    $game = Game::factory()->create(['name' => 'Sky: Children of the Light', 'igdb_id' => 65503, 'trailers' => null]);
    attachGame($list, $game, ['video_url' => null]);

    resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=skyId');
});

it('falls back to the newest IGDB trailer (highest id) when no channel video matches', function () {
    fakeTrailerSources([
        channelItem('Totally Unrelated Game - Future Games Show', 'nope', '2026-06-06T20:00:00Z'),
    ]);

    $list = trailerEvent();
    $game = Game::factory()->create([
        'name' => 'Blight Survival',
        'igdb_id' => 211470,
        'trailers' => [['id' => 1, 'video_id' => 'oldTrailer'], ['id' => 9, 'video_id' => 'newTrailer']],
    ]);
    attachGame($list, $game, ['video_url' => null]);

    $report = resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=newTrailer')
        ->and($report['igdb'])->toBe(1);
});

it('refreshes stale trailers from IGDB and picks the newly-added reveal', function () {
    fakeTrailerSources(
        [channelItem('Nothing matches here', 'nope', '2026-06-06T20:00:00Z')],
        [65503 => [['id' => 1, 'video_id' => 'staleTrailer'], ['id' => 186662, 'video_id' => 'freshReveal']]],
    );

    $list = trailerEvent();
    $game = Game::factory()->create([
        'name' => 'Sky: Children of the Light',
        'igdb_id' => 65503,
        'trailers' => [['id' => 1, 'video_id' => 'staleTrailer']],
    ]);
    attachGame($list, $game, ['video_url' => 'https://www.youtube.com/watch?v=staleTrailer']);

    resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=freshReveal');
});

it('never overwrites an admin-set (manual) trailer', function () {
    fakeTrailerSources([
        channelItem('EXODUS Reveal - Future Games Show', 'chanVid', '2026-06-06T20:00:00Z'),
    ]);

    $list = trailerEvent();
    $game = Game::factory()->create(['name' => 'EXODUS', 'igdb_id' => 279621]);
    attachGame($list, $game, ['video_url' => 'https://www.youtube.com/watch?v=CURATED', 'video_url_manual' => true]);

    $report = resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=CURATED')
        ->and($report['matched'])->toBe(0);
});

it('does not match a video whose title only partially contains the game name', function () {
    fakeTrailerSources([
        channelItem('Outlaws of the Old West - Gameplay', 'wrongVid', '2026-06-06T20:00:00Z'),
    ]);

    $list = trailerEvent();
    $game = Game::factory()->create(['name' => 'Star Wars Outlaws', 'igdb_id' => 999, 'trailers' => null]);
    attachGame($list, $game, ['video_url' => null]);

    resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBeNull();
});

it('lets the channel reveal win over the IGDB trailer', function () {
    fakeTrailerSources([
        channelItem('EXODUS Extended Gameplay - Future Games Show', 'chanVid', '2026-06-06T20:00:00Z'),
    ]);

    $list = trailerEvent();
    $game = Game::factory()->create(['name' => 'EXODUS', 'igdb_id' => 279621, 'trailers' => [['id' => 9, 'video_id' => 'igdbVid']]]);
    attachGame($list, $game, ['video_url' => null]);

    $report = resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=chanVid')
        ->and($report['channel'])->toBe(1);
});

it('anchors the trailer window on event_data event_time/timezone, not start_at', function () {
    fakeTrailerSources([
        channelItem('EXODUS Reveal - Future Games Show', 'revealId', '2026-06-06T19:00:00Z'),
        channelItem('EXODUS Teaser', 'teaserId', '2026-06-06T12:00:00Z'),
    ]);

    $list = GameList::factory()->events()->system()->create([
        'start_at' => Carbon::parse('2026-06-06T02:00:00Z'), // deliberately wrong / early
        'event_data' => [
            'youtube_channel_url' => 'https://www.youtube.com/@FGS/videos',
            'event_time' => '2026-06-06 14:00:00',
            'event_timezone' => 'America/New_York', // 18:00 UTC
        ],
    ]);
    $game = Game::factory()->create(['name' => 'EXODUS', 'igdb_id' => 279621, 'trailers' => null]);
    attachGame($list, $game, ['video_url' => null]);

    resolveTrailers($list);

    // The 12:00 teaser predates the real (event_data) start and is excluded; anchoring on the
    // wrong start_at (02:00 UTC) would have made it the earliest in-window match instead.
    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=revealId');
});

it('sets nothing when there is no channel url and the game has no trailers', function () {
    fakeTrailerSources([]);

    $list = trailerEvent(channelUrl: null);
    $game = Game::factory()->create(['name' => 'EXODUS', 'igdb_id' => 279621, 'trailers' => null]);
    attachGame($list, $game, ['video_url' => null]);

    $report = resolveTrailers($list);

    expect($list->games()->first()->pivot->video_url)->toBeNull()
        ->and($report)->toBe(['matched' => 0, 'channel' => 0, 'igdb' => 0, 'scanned' => 0]);
});
