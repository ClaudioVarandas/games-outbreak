<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YoutubeDataService
{
    private const ENDPOINT = 'https://www.googleapis.com/youtube/v3/videos';

    private const CHANNELS_ENDPOINT = 'https://www.googleapis.com/youtube/v3/channels';

    private const PLAYLIST_ITEMS_ENDPOINT = 'https://www.googleapis.com/youtube/v3/playlistItems';

    private const YOUTUBE_ID_PATTERN = '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/';

    public function extractYoutubeId(string $url): ?string
    {
        return preg_match(self::YOUTUBE_ID_PATTERN, $url, $matches) === 1
            ? $matches[1]
            : null;
    }

    public function extractChannelHandle(string $value): ?string
    {
        if (preg_match('/@([A-Za-z0-9._-]+)/', $value, $matches) === 1) {
            return $matches[1];
        }

        $trimmed = trim($value);

        return ($trimmed !== '' && ! str_contains($trimmed, '/')) ? ltrim($trimmed, '@') : null;
    }

    /**
     * Fetch a channel's most-recent uploads (newest first) from its @handle or channel URL.
     *
     * @return list<array{video_id: string, title: string, published_at: ?Carbon}>
     */
    public function recentChannelVideos(string $channelUrlOrHandle, int $limit = 50): array
    {
        $apiKey = config('services.youtube.api_key');

        if (! $apiKey) {
            throw new RuntimeException('YOUTUBE_API_KEY is not configured.');
        }

        $handle = $this->extractChannelHandle($channelUrlOrHandle);

        if ($handle === null) {
            return [];
        }

        $channelResponse = Http::get(self::CHANNELS_ENDPOINT, [
            'part' => 'contentDetails',
            'forHandle' => $handle,
            'key' => $apiKey,
        ]);

        if ($channelResponse->failed()) {
            throw new RuntimeException('YouTube channels request failed: '.$channelResponse->status().' '.$channelResponse->body());
        }

        $uploadsPlaylist = $channelResponse->json('items.0.contentDetails.relatedPlaylists.uploads');

        if (! $uploadsPlaylist) {
            return [];
        }

        $playlistResponse = Http::get(self::PLAYLIST_ITEMS_ENDPOINT, [
            'part' => 'snippet',
            'playlistId' => $uploadsPlaylist,
            'maxResults' => min($limit, 50),
            'key' => $apiKey,
        ]);

        if ($playlistResponse->failed()) {
            throw new RuntimeException('YouTube playlistItems request failed: '.$playlistResponse->status().' '.$playlistResponse->body());
        }

        return collect($playlistResponse->json('items', []))
            ->map(fn (array $item): array => [
                'video_id' => $item['snippet']['resourceId']['videoId'] ?? null,
                'title' => $item['snippet']['title'] ?? '',
                'published_at' => isset($item['snippet']['publishedAt'])
                    ? Carbon::parse($item['snippet']['publishedAt'])
                    : null,
            ])
            ->filter(fn (array $video): bool => ! empty($video['video_id']))
            ->values()
            ->all();
    }

    /**
     * @return array{title: ?string, channel_name: ?string, channel_id: ?string, duration_seconds: ?int, thumbnail_url: ?string, description: ?string, published_at: ?Carbon, raw: array}
     */
    public function fetchVideo(string $youtubeId): array
    {
        $apiKey = config('services.youtube.api_key');

        if (! $apiKey) {
            throw new RuntimeException('YOUTUBE_API_KEY is not configured.');
        }

        $response = Http::get(self::ENDPOINT, [
            'part' => 'snippet,contentDetails',
            'id' => $youtubeId,
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('YouTube API request failed: '.$response->status().' '.$response->body());
        }

        $payload = $response->json();
        $item = $payload['items'][0] ?? null;

        if (! $item) {
            throw new RuntimeException('YouTube video not found: '.$youtubeId);
        }

        $snippet = $item['snippet'] ?? [];
        $contentDetails = $item['contentDetails'] ?? [];
        $thumbnails = $snippet['thumbnails'] ?? [];
        $thumbnail = $thumbnails['maxres']['url']
            ?? $thumbnails['high']['url']
            ?? $thumbnails['medium']['url']
            ?? $thumbnails['default']['url']
            ?? null;

        return [
            'title' => $snippet['title'] ?? null,
            'channel_name' => $snippet['channelTitle'] ?? null,
            'channel_id' => $snippet['channelId'] ?? null,
            'duration_seconds' => isset($contentDetails['duration'])
                ? $this->parseIsoDuration($contentDetails['duration'])
                : null,
            'thumbnail_url' => $thumbnail,
            'description' => isset($snippet['description'])
                ? mb_substr((string) $snippet['description'], 0, 5000)
                : null,
            'published_at' => isset($snippet['publishedAt'])
                ? Carbon::parse($snippet['publishedAt'])
                : null,
            'raw' => $payload,
        ];
    }

    public function parseIsoDuration(string $iso): int
    {
        if (! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
            return 0;
        }

        $hours = (int) ($m[1] ?? 0);
        $minutes = (int) ($m[2] ?? 0);
        $seconds = (int) ($m[3] ?? 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }
}
