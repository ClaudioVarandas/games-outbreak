<?php

use App\Models\Game;
use App\Models\GameList;
use App\Services\ChannelTrailerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function fakeChannelVideos(array $videos): void
{
    config(['services.youtube.api_key' => 'test-key']);
    Http::fake([
        'googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU_fgs']]]],
        ], 200),
        'googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => $videos], 200),
    ]);
}

function channelVideo(string $title, string $videoId, string $publishedAt): array
{
    return ['snippet' => ['title' => $title, 'publishedAt' => $publishedAt, 'resourceId' => ['videoId' => $videoId]]];
}

function eventWithChannel(): GameList
{
    return GameList::factory()->events()->system()->create([
        'event_data' => ['youtube_channel_url' => 'https://www.youtube.com/@FutureGamesShow/videos'],
    ]);
}

it('sets a game pivot video_url from a matching channel video, replacing the IGDB one', function () {
    fakeChannelVideos([
        channelVideo('EXODUS Extended Gameplay | Future Games Show', 'exoVid', '2026-06-06T19:00:00Z'),
    ]);

    $list = eventWithChannel();
    $game = Game::factory()->create(['name' => 'EXODUS']);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => 'https://www.youtube.com/watch?v=igdbOld', 'video_url_manual' => false]);

    $report = app(ChannelTrailerService::class)->syncFromChannel($list);

    expect($report['matched'])->toBe(1)
        ->and($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=exoVid');
});

it('fills an empty trailer from a matching channel video', function () {
    fakeChannelVideos([
        channelVideo('Star Wars Outlaws — Official Trailer', 'swoVid', '2026-06-06T19:00:00Z'),
    ]);

    $list = eventWithChannel();
    $game = Game::factory()->create(['name' => 'Star Wars Outlaws']);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => null]);

    app(ChannelTrailerService::class)->syncFromChannel($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=swoVid');
});

it('never overwrites an admin-set (manual) trailer', function () {
    fakeChannelVideos([
        channelVideo('EXODUS Extended Gameplay', 'exoVid', '2026-06-06T19:00:00Z'),
    ]);

    $list = eventWithChannel();
    $game = Game::factory()->create(['name' => 'EXODUS']);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => 'https://www.youtube.com/watch?v=CURATED', 'video_url_manual' => true]);

    $report = app(ChannelTrailerService::class)->syncFromChannel($list);

    expect($report['matched'])->toBe(0)
        ->and($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=CURATED');
});

it('does not match a video whose title only partially contains a word', function () {
    fakeChannelVideos([
        channelVideo('Outlaws of the Old West - Gameplay', 'wrongVid', '2026-06-06T19:00:00Z'),
    ]);

    $list = eventWithChannel();
    $game = Game::factory()->create(['name' => 'Star Wars Outlaws']);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => null]);

    $report = app(ChannelTrailerService::class)->syncFromChannel($list);

    expect($report['matched'])->toBe(0)
        ->and($list->games()->first()->pivot->video_url)->toBeNull();
});

it('picks the most recent matching video when several match', function () {
    fakeChannelVideos([
        channelVideo('EXODUS Final Trailer', 'newVid', '2026-06-06T20:00:00Z'),
        channelVideo('EXODUS Teaser', 'oldVid', '2026-06-06T18:00:00Z'),
    ]);

    $list = eventWithChannel();
    $game = Game::factory()->create(['name' => 'EXODUS']);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => null]);

    app(ChannelTrailerService::class)->syncFromChannel($list);

    expect($list->games()->first()->pivot->video_url)->toBe('https://www.youtube.com/watch?v=newVid');
});

it('returns zero when the event has no channel url', function () {
    fakeChannelVideos([channelVideo('EXODUS', 'x', '2026-06-06T19:00:00Z')]);

    $list = GameList::factory()->events()->system()->create(['event_data' => []]);
    $game = Game::factory()->create(['name' => 'EXODUS']);
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => null]);

    expect(app(ChannelTrailerService::class)->syncFromChannel($list))->toBe(['matched' => 0, 'scanned' => 0]);
});
