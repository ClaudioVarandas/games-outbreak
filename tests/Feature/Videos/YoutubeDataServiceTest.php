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
