<?php

use App\Jobs\Broadcasts\BroadcastNewsArticleJob;
use App\Jobs\Broadcasts\BroadcastVideoJob;
use App\Models\NewsArticle;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

it('re-dispatches a news broadcast with force=true', function () {
    $article = NewsArticle::factory()->published()->create();

    $this->artisan('broadcast:resend', ['type' => 'news', 'id' => $article->id])
        ->assertSuccessful();

    Bus::assertDispatched(BroadcastNewsArticleJob::class, fn ($j) => $j->newsArticleId === $article->id && $j->force === true);
});

it('re-dispatches a video broadcast with force=true', function () {
    $video = Video::factory()->ready()->create();

    $this->artisan('broadcast:resend', ['type' => 'video', 'id' => $video->id])
        ->assertSuccessful();

    Bus::assertDispatched(BroadcastVideoJob::class, fn ($j) => $j->videoId === $video->id && $j->force === true);
});

it('fails when the record does not exist', function () {
    $this->artisan('broadcast:resend', ['type' => 'news', 'id' => 9999])
        ->assertExitCode(1);

    Bus::assertNothingDispatched();
});

it('rejects unknown type', function () {
    $this->artisan('broadcast:resend', ['type' => 'podcast', 'id' => 1])
        ->assertExitCode(2);

    Bus::assertNothingDispatched();
});

it('rejects x channel as not yet implemented', function () {
    $article = NewsArticle::factory()->published()->create();

    $this->artisan('broadcast:resend', ['type' => 'news', 'id' => $article->id, '--channel' => 'x'])
        ->assertExitCode(2);

    Bus::assertNothingDispatched();
});

it('rejects unknown channel', function () {
    $article = NewsArticle::factory()->published()->create();

    $this->artisan('broadcast:resend', ['type' => 'news', 'id' => $article->id, '--channel' => 'discord'])
        ->assertExitCode(2);

    Bus::assertNothingDispatched();
});
