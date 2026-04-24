<?php

use App\Actions\Videos\MaybeBroadcastVideo;
use App\Jobs\Broadcasts\BroadcastVideoJob;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

it('dispatches broadcast when a video is Ready + active + should_broadcast + not yet broadcasted', function () {
    $video = Video::factory()->ready()->create();

    app(MaybeBroadcastVideo::class)->handle($video);

    Bus::assertDispatched(BroadcastVideoJob::class, fn ($j) => $j->videoId === $video->id);
});

it('skips if already broadcasted', function () {
    $video = Video::factory()->ready()->create(['broadcasted_at' => now()]);

    app(MaybeBroadcastVideo::class)->handle($video);

    Bus::assertNotDispatched(BroadcastVideoJob::class);
});

it('skips if should_broadcast is false', function () {
    $video = Video::factory()->ready()->create(['should_broadcast' => false]);

    app(MaybeBroadcastVideo::class)->handle($video);

    Bus::assertNotDispatched(BroadcastVideoJob::class);
});

it('skips if inactive', function () {
    $video = Video::factory()->ready()->inactive()->create();

    app(MaybeBroadcastVideo::class)->handle($video);

    Bus::assertNotDispatched(BroadcastVideoJob::class);
});

it('dispatches on toggle-active that switches inactive → active', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $video = Video::factory()->ready()->inactive()->create();

    $this->actingAs($admin)
        ->patch(route('admin.videos.toggle-active', $video))
        ->assertRedirect();

    Bus::assertDispatched(BroadcastVideoJob::class, fn ($j) => $j->videoId === $video->id);
});

it('does NOT dispatch when toggle-active switches active → inactive', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $video = Video::factory()->ready()->create(); // is_active default true

    $this->actingAs($admin)
        ->patch(route('admin.videos.toggle-active', $video))
        ->assertRedirect();

    Bus::assertNotDispatched(BroadcastVideoJob::class);
});

it('allows admin to flip the should_broadcast toggle', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $video = Video::factory()->create(['should_broadcast' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.videos.toggle-should-broadcast', $video))
        ->assertRedirect();

    expect($video->fresh()->should_broadcast)->toBeFalse();
});
