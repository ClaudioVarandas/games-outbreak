<?php

use App\Actions\Videos\FetchYoutubeVideoMetadata;
use App\Enums\VideoImportStatusEnum;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.youtube.api_key' => 'test-key']);
});

it('populates the video and marks it ready on success', function () {
    Http::fake([
        'googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [[
                'snippet' => [
                    'title' => 'Gameplay Reveal',
                    'channelTitle' => 'BANDAI NAMCO',
                    'channelId' => 'UCbn',
                    'description' => 'Elden Ring trailer.',
                    'publishedAt' => '2024-05-12T10:00:00Z',
                    'thumbnails' => ['maxres' => ['url' => 'https://i.ytimg.com/vi/E3/maxresdefault.jpg']],
                ],
                'contentDetails' => ['duration' => 'PT3M35S'],
            ]],
        ], 200),
    ]);

    $video = Video::factory()->create(['youtube_id' => 'E3Huy2cdih0']);

    app(FetchYoutubeVideoMetadata::class)->handle($video->fresh());

    $video->refresh();

    expect($video->status)->toBe(VideoImportStatusEnum::Ready)
        ->and($video->title)->toBe('Gameplay Reveal')
        ->and($video->channel_name)->toBe('BANDAI NAMCO')
        ->and($video->duration_seconds)->toBe(215)
        ->and($video->thumbnail_url)->toBe('https://i.ytimg.com/vi/E3/maxresdefault.jpg')
        ->and($video->published_at->toIso8601String())->toContain('2024-05-12')
        ->and($video->raw_api_response)->toBeArray();
});

it('marks the video as failed when the API errors out', function () {
    Http::fake([
        'googleapis.com/youtube/v3/videos*' => Http::response(['error' => 'notFound'], 404),
    ]);

    $video = Video::factory()->create(['youtube_id' => 'bad']);

    app(FetchYoutubeVideoMetadata::class)->handle($video->fresh());

    $video->refresh();

    expect($video->status)->toBe(VideoImportStatusEnum::Failed)
        ->and($video->failure_reason)->not->toBeNull();
});

it('fails fast when video has no youtube_id', function () {
    $video = Video::factory()->create(['youtube_id' => null]);

    app(FetchYoutubeVideoMetadata::class)->handle($video->fresh());

    expect($video->fresh()->status)->toBe(VideoImportStatusEnum::Failed);
});
