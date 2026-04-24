<?php

use App\Jobs\Broadcasts\BroadcastVideoJob;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.enabled' => true,
        'services.telegram.bot_token' => 'TOKEN',
        'services.telegram.chat_id' => 'CHAT',
    ]);
});

it('sends a photo with caption for a Ready active video', function () {
    Http::fake(['*/sendPhoto' => Http::response(['ok' => true], 200)]);

    $video = Video::factory()->ready()->create([
        'title' => 'Gameplay Demo',
        'channel_name' => 'Rockstar',
        'thumbnail_url' => 'https://i.ytimg.com/abc.jpg',
        'duration_seconds' => 91,
    ]);

    BroadcastVideoJob::dispatchSync($video->id);

    Http::assertSent(function ($req) {
        return str_contains($req->url(), '/sendPhoto')
            && $req['photo'] === 'https://i.ytimg.com/abc.jpg'
            && str_contains($req['caption'], '🎬')
            && str_contains($req['caption'], 'Rockstar')
            && str_contains($req['caption'], 'Ver no YouTube');
    });

    expect($video->fresh()->broadcasted_at)->not->toBeNull();
});

it('skips when status is not Ready', function () {
    Http::fake();
    $video = Video::factory()->create();

    BroadcastVideoJob::dispatchSync($video->id);

    Http::assertNothingSent();
});

it('skips when is_active is false', function () {
    Http::fake();
    $video = Video::factory()->ready()->inactive()->create();

    BroadcastVideoJob::dispatchSync($video->id);

    Http::assertNothingSent();
});

it('skips when should_broadcast is false', function () {
    Http::fake();
    $video = Video::factory()->ready()->create(['should_broadcast' => false]);

    BroadcastVideoJob::dispatchSync($video->id);

    Http::assertNothingSent();
});

it('skips when already broadcasted and not forced', function () {
    Http::fake();
    $video = Video::factory()->ready()->create(['broadcasted_at' => now()->subHour()]);

    BroadcastVideoJob::dispatchSync($video->id);

    Http::assertNothingSent();
});

it('forces a resend when $force is true', function () {
    Http::fake(['*/sendPhoto' => Http::response(['ok' => true], 200)]);
    $video = Video::factory()->ready()->create(['broadcasted_at' => now()->subDay()]);

    BroadcastVideoJob::dispatchSync($video->id, force: true);

    Http::assertSentCount(1);
});
