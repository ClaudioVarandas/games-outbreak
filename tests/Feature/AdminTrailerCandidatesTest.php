<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    config(['services.youtube.api_key' => 'test-key']);
});

function tcChannelItem(string $title, string $videoId, string $publishedAt): array
{
    return ['snippet' => ['title' => $title, 'publishedAt' => $publishedAt, 'resourceId' => ['videoId' => $videoId]]];
}

/**
 * @param  array<int, array<string, mixed>>  $channelItems
 * @param  array<int, array<string, mixed>>  $searchItems
 */
function tcFakeSources(array $channelItems = [], array $searchItems = []): void
{
    Http::fake(function ($request) use ($channelItems, $searchItems) {
        $url = $request->url();

        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, 'youtube/v3/channels')) {
            return Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU']]]]], 200);
        }
        if (str_contains($url, 'youtube/v3/playlistItems')) {
            return Http::response(['items' => $channelItems], 200);
        }
        if (str_contains($url, 'youtube/v3/search')) {
            return Http::response(['items' => $searchItems], 200);
        }

        return Http::response([], 200);
    });
}

function tcEventList(Game $game, ?string $channelUrl = 'https://www.youtube.com/@FGS/videos'): GameList
{
    $list = GameList::factory()->events()->system()->create([
        'start_at' => Carbon::parse('2026-06-06T19:00:00Z'),
        'event_data' => $channelUrl ? ['youtube_channel_url' => $channelUrl] : [],
    ]);
    $list->games()->attach($game->id, ['order' => 1]);

    return $list;
}

function tcCandidatesUrl(GameList $list, Game $game): string
{
    return route('admin.system-lists.games.trailer-candidates', [
        $list->list_type->toSlug(), $list->slug, $game->id,
    ]);
}

it('returns ordered trailer candidates for a game in an event list', function () {
    tcFakeSources([
        tcChannelItem('EXODUS Extended Gameplay - Future Games Show', 'chanVid', '2026-06-06T20:00:00Z'),
    ]);

    $game = Game::factory()->create(['name' => 'EXODUS', 'igdb_id' => 279621, 'trailers' => [['id' => 9, 'video_id' => 'igdbVid']]]);
    $list = tcEventList($game);

    $response = $this->actingAs($this->admin)->getJson(tcCandidatesUrl($list, $game));

    $response->assertOk()
        ->assertJsonStructure(['candidates' => [['video_id', 'url', 'title', 'source', 'channel_name', 'published_at', 'thumbnail_url']]]);

    $candidates = $response->json('candidates');

    expect($candidates[0]['source'])->toBe('channel')
        ->and($candidates[0]['video_id'])->toBe('chanVid')
        ->and($candidates[1]['source'])->toBe('igdb');
});

it('only includes youtube-search candidates when channel and igdb are empty', function () {
    tcFakeSources([], [
        ['id' => ['videoId' => 'searchVid'], 'snippet' => ['title' => 'EXODUS Trailer', 'publishedAt' => '2026-06-01T10:00:00Z', 'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/x.jpg']]]],
    ]);

    $game = Game::factory()->create(['name' => 'EXODUS', 'igdb_id' => 279621, 'trailers' => null]);
    $list = tcEventList($game);

    $candidates = $this->actingAs($this->admin)
        ->getJson(tcCandidatesUrl($list, $game))
        ->json('candidates');

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['source'])->toBe('search')
        ->and($candidates[0]['video_id'])->toBe('searchVid');
});

it('returns 404 for non-event lists', function () {
    $game = Game::factory()->create();
    $list = GameList::factory()->create(['list_type' => ListTypeEnum::YEARLY, 'is_system' => true]);
    $list->games()->attach($game->id, ['order' => 1]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.system-lists.games.trailer-candidates', ['yearly', $list->slug, $game->id]))
        ->assertNotFound();
});

it('returns 404 when the game is not in the list', function () {
    tcFakeSources();
    $game = Game::factory()->create();
    $list = GameList::factory()->events()->system()->create(['event_data' => []]);

    $this->actingAs($this->admin)
        ->getJson(tcCandidatesUrl($list, $game))
        ->assertNotFound();
});

it('returns an empty candidate list gracefully when youtube is unconfigured', function () {
    config(['services.youtube.api_key' => null]);
    Http::fake();

    $game = Game::factory()->create(['name' => 'EXODUS', 'trailers' => null]);
    $list = tcEventList($game);

    $this->actingAs($this->admin)
        ->getJson(tcCandidatesUrl($list, $game))
        ->assertOk()
        ->assertJson(['candidates' => []]);
});

it('blocks guests', function () {
    $game = Game::factory()->create();
    $list = tcEventList($game);

    $this->getJson(tcCandidatesUrl($list, $game))->assertUnauthorized();
});

it('blocks non-admin users', function () {
    $game = Game::factory()->create();
    $list = tcEventList($game);

    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->getJson(tcCandidatesUrl($list, $game))
        ->assertForbidden();
});
