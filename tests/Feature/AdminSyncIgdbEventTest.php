<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function fakeIgdbForSync(array $gameIds): void
{
    Http::fake(function ($request) use ($gameIds) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            return Http::response([[
                'id' => 137,
                'name' => 'Summer Game Fest',
                'slug' => 'summer-game-fest',
                'start_time' => 1749500000,
                'games' => $gameIds,
            ]], 200);
        }
        if (str_contains($url, '/v4/games')) {
            preg_match('/where id = (\d+)/', $request->body(), $m);
            $id = (int) ($m[1] ?? 0);

            return Http::response($id ? [[
                'id' => $id,
                'name' => "Game {$id}",
                'cover' => ['image_id' => 'co'.$id],
                'platforms' => [['id' => 6, 'name' => 'PC']],
                'genres' => [],
                'game_modes' => [],
                'external_games' => [],
                'websites' => [],
                'game_type' => 0,
                'release_dates' => null,
            ]] : [], 200);
        }

        return Http::response([], 200);
    });
}

function syncUrl(GameList $list): string
{
    return "/admin/system-lists/events/{$list->slug}";
}

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Queue::fake();
});

it('syncs games from IGDB into the events list', function () {
    fakeIgdbForSync([111, 222]);
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-game-fest',
        'igdb_event_id' => 137,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(syncUrl($list).'/sync-igdb');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('added', 2);

    expect($list->games()->count())->toBe(2);
});

it('matches channel trailers when the Sync button runs', function () {
    config(['services.youtube.api_key' => 'test-key']);

    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            return Http::response([['id' => 137, 'name' => 'Summer Game Fest', 'start_time' => 1749500000, 'games' => [111]]], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([['id' => 111, 'name' => 'EXODUS', 'cover' => ['image_id' => 'co111'], 'platforms' => [], 'genres' => [], 'game_modes' => [], 'external_games' => [], 'websites' => [], 'game_type' => 0, 'release_dates' => null]], 200);
        }
        if (str_contains($url, 'youtube/v3/channels')) {
            return Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU']]]]], 200);
        }
        if (str_contains($url, 'youtube/v3/playlistItems')) {
            return Http::response(['items' => [['snippet' => ['title' => 'EXODUS Reveal', 'publishedAt' => '2026-06-06T19:00:00Z', 'resourceId' => ['videoId' => 'exoVid']]]]], 200);
        }

        return Http::response([], 200);
    });

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-game-fest',
        'igdb_event_id' => 137,
        'event_data' => ['youtube_channel_url' => 'https://www.youtube.com/@SummerGameFest/videos'],
    ]);

    $response = $this->actingAs($admin)->postJson(syncUrl($list).'/sync-igdb');

    $response->assertSuccessful()->assertJsonPath('trailers_matched', 1);

    expect($list->games()->where('games.igdb_id', 111)->first()->pivot->video_url)
        ->toBe('https://www.youtube.com/watch?v=exoVid');
});

it('only adds newly-appeared games on a re-sync', function () {
    fakeIgdbForSync([111, 222]);
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-game-fest',
        'igdb_event_id' => 137,
    ]);
    $existing = Game::factory()->create(['igdb_id' => 111]);
    $list->games()->attach($existing->id, ['order' => 1, 'platforms' => json_encode([6])]);

    $response = $this->actingAs($admin)->postJson(syncUrl($list).'/sync-igdb');

    $response->assertSuccessful()
        ->assertJsonPath('added', 1)
        ->assertJsonPath('skipped', 1);

    expect($list->games()->count())->toBe(2);
});

it('fails when the list has no IGDB event id', function () {
    fakeIgdbForSync([111]);
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'no-id-event',
        'igdb_event_id' => null,
    ]);

    $this->actingAs($admin)
        ->postJson(syncUrl($list).'/sync-igdb')
        ->assertStatus(422);
});

it('fails when IGDB has no such event', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/events' => Http::response([], 200),
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'gone-event',
        'igdb_event_id' => 999,
    ]);

    $this->actingAs($admin)
        ->postJson(syncUrl($list).'/sync-igdb')
        ->assertStatus(422);
});

it('forbids non-admin users', function () {
    fakeIgdbForSync([111]);
    $user = User::factory()->create(['is_admin' => false]);
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-game-fest',
        'igdb_event_id' => 137,
    ]);

    $this->actingAs($user)
        ->postJson(syncUrl($list).'/sync-igdb')
        ->assertForbidden();
});
