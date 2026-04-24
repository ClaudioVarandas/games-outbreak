<?php

use App\Actions\Videos\CreateVideo;
use App\Actions\Videos\FetchYoutubeVideoMetadata;
use App\Enums\VideoImportStatusEnum;
use App\Jobs\Videos\ImportYoutubeVideoJob;
use App\Models\User;
use App\Models\Video;
use App\Services\YoutubeDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.youtube.api_key' => 'test-key']);

    Http::fake([
        'googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [[
                'snippet' => [
                    'title' => 'T',
                    'channelTitle' => 'C',
                    'channelId' => 'UC',
                    'publishedAt' => '2025-01-01T00:00:00Z',
                    'thumbnails' => ['high' => ['url' => 'https://t/h']],
                ],
                'contentDetails' => ['duration' => 'PT1M'],
            ]],
        ], 200),
    ]);
});

it('creates a Ready video when given a valid YouTube URL', function () {
    $user = User::factory()->create();

    (new ImportYoutubeVideoJob('https://www.youtube.com/watch?v=QdBZY2fkU-0', $user->id))
        ->handle(
            app(YoutubeDataService::class),
            app(CreateVideo::class),
            app(FetchYoutubeVideoMetadata::class),
        );

    $video = Video::first();

    expect($video)->not->toBeNull()
        ->and($video->youtube_id)->toBe('QdBZY2fkU-0')
        ->and($video->status)->toBe(VideoImportStatusEnum::Ready)
        ->and($video->user_id)->toBe($user->id);
});

it('creates a Failed row when the URL is not a YouTube link', function () {
    $user = User::factory()->create();

    (new ImportYoutubeVideoJob('https://example.com/not-yt', $user->id))
        ->handle(
            app(YoutubeDataService::class),
            app(CreateVideo::class),
            app(FetchYoutubeVideoMetadata::class),
        );

    $video = Video::first();

    expect($video)->not->toBeNull()
        ->and($video->youtube_id)->toBeNull()
        ->and($video->status)->toBe(VideoImportStatusEnum::Failed);
});

it('skips import when the same youtube_id already exists', function () {
    $user = User::factory()->create();
    Video::factory()->create(['youtube_id' => 'QdBZY2fkU-0']);

    (new ImportYoutubeVideoJob('https://youtu.be/QdBZY2fkU-0', $user->id))
        ->handle(
            app(YoutubeDataService::class),
            app(CreateVideo::class),
            app(FetchYoutubeVideoMetadata::class),
        );

    expect(Video::count())->toBe(1);
});
