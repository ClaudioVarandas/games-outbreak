<?php

use App\Services\YoutubeDataService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.youtube.api_key' => 'test-key']);
});

it('extracts YouTube IDs from common URL formats', function (string $url, string $expectedId) {
    expect((new YoutubeDataService)->extractYoutubeId($url))->toBe($expectedId);
})->with([
    'watch' => ['https://www.youtube.com/watch?v=QdBZY2fkU-0', 'QdBZY2fkU-0'],
    'short url' => ['https://youtu.be/QdBZY2fkU-0', 'QdBZY2fkU-0'],
    'shorts' => ['https://www.youtube.com/shorts/QdBZY2fkU-0', 'QdBZY2fkU-0'],
    'embed' => ['https://www.youtube.com/embed/QdBZY2fkU-0', 'QdBZY2fkU-0'],
    'watch with extra params' => ['https://www.youtube.com/watch?v=QdBZY2fkU-0&t=15', 'QdBZY2fkU-0'],
]);

it('returns null when URL is not a YouTube link', function () {
    expect((new YoutubeDataService)->extractYoutubeId('https://vimeo.com/123'))->toBeNull();
});

it('parses ISO 8601 durations to seconds', function (string $iso, int $expected) {
    expect((new YoutubeDataService)->parseIsoDuration($iso))->toBe($expected);
})->with([
    ['PT30S', 30],
    ['PT1M', 60],
    ['PT4M46S', 286],
    ['PT1H', 3600],
    ['PT1H2M3S', 3723],
    ['garbage', 0],
]);

it('fetches video metadata from YouTube Data API', function () {
    Http::fake([
        'googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [[
                'snippet' => [
                    'title' => 'Test Trailer',
                    'channelTitle' => 'Rockstar Games',
                    'channelId' => 'UC123',
                    'description' => 'A big game.',
                    'publishedAt' => '2025-12-01T10:00:00Z',
                    'thumbnails' => [
                        'maxres' => ['url' => 'https://i.ytimg.com/vi/abc/maxresdefault.jpg'],
                        'high' => ['url' => 'https://i.ytimg.com/vi/abc/hqdefault.jpg'],
                    ],
                ],
                'contentDetails' => ['duration' => 'PT1M31S'],
            ]],
        ], 200),
    ]);

    $data = (new YoutubeDataService)->fetchVideo('QdBZY2fkU-0');

    expect($data['title'])->toBe('Test Trailer')
        ->and($data['channel_name'])->toBe('Rockstar Games')
        ->and($data['channel_id'])->toBe('UC123')
        ->and($data['duration_seconds'])->toBe(91)
        ->and($data['thumbnail_url'])->toBe('https://i.ytimg.com/vi/abc/maxresdefault.jpg')
        ->and($data['published_at']->toIso8601String())->toContain('2025-12-01')
        ->and($data['raw'])->toBeArray();
});

it('throws when API key is missing', function () {
    config(['services.youtube.api_key' => null]);
    (new YoutubeDataService)->fetchVideo('abc123');
})->throws(RuntimeException::class, 'YOUTUBE_API_KEY');

it('throws when API returns an empty item list', function () {
    Http::fake([
        'googleapis.com/youtube/v3/videos*' => Http::response(['items' => []], 200),
    ]);
    (new YoutubeDataService)->fetchVideo('notfound');
})->throws(RuntimeException::class, 'not found');

it('throws on API failure', function () {
    Http::fake([
        'googleapis.com/youtube/v3/videos*' => Http::response(['error' => 'quotaExceeded'], 403),
    ]);
    (new YoutubeDataService)->fetchVideo('abc');
})->throws(RuntimeException::class);

it('extracts the channel handle from a channel videos url', function () {
    $svc = new YoutubeDataService;

    expect($svc->extractChannelHandle('https://www.youtube.com/@FutureGamesShow/videos'))->toBe('FutureGamesShow')
        ->and($svc->extractChannelHandle('@FutureGamesShow'))->toBe('FutureGamesShow')
        ->and($svc->extractChannelHandle('FutureGamesShow'))->toBe('FutureGamesShow')
        ->and($svc->extractChannelHandle(''))->toBeNull();
});

it('returns the recent uploads for a channel handle url', function () {
    Http::fake([
        'googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU_fgs']]]],
        ], 200),
        'googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'items' => [
                ['snippet' => ['title' => 'EXODUS Extended Gameplay', 'publishedAt' => '2026-06-06T19:30:00Z', 'resourceId' => ['videoId' => 'vid1']]],
                ['snippet' => ['title' => 'Some Other Trailer', 'publishedAt' => '2026-06-06T19:00:00Z', 'resourceId' => ['videoId' => 'vid2']]],
            ],
        ], 200),
    ]);

    $videos = (new YoutubeDataService)->recentChannelVideos('https://www.youtube.com/@FutureGamesShow/videos');

    expect($videos)->toHaveCount(2)
        ->and($videos[0]['video_id'])->toBe('vid1')
        ->and($videos[0]['title'])->toBe('EXODUS Extended Gameplay');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'forHandle=FutureGamesShow'));
});

it('returns empty when the channel handle cannot be resolved', function () {
    Http::fake([
        'googleapis.com/youtube/v3/channels*' => Http::response(['items' => []], 200),
    ]);

    expect((new YoutubeDataService)->recentChannelVideos('https://www.youtube.com/@Unknown/videos'))->toBe([]);
});
