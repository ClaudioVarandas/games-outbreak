<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameList;

class ChannelTrailerService
{
    public function __construct(private YoutubeDataService $youtube) {}

    /**
     * Match the event's YouTube channel recent uploads to its games by title and set
     * each game's pivot video_url. The channel trailer is preferred over an IGDB-derived
     * one and fills empty slots, but an admin-set (manual) trailer is never touched.
     *
     * @return array{matched: int, scanned: int}
     */
    public function syncFromChannel(GameList $list): array
    {
        $channelUrl = $list->event_data['youtube_channel_url'] ?? null;

        if (! $channelUrl) {
            return ['matched' => 0, 'scanned' => 0];
        }

        $videos = $this->youtube->recentChannelVideos($channelUrl);

        if ($videos === []) {
            return ['matched' => 0, 'scanned' => 0];
        }

        $matched = 0;

        foreach ($list->games()->get() as $game) {
            if ($game->pivot->video_url_manual) {
                continue;
            }

            $videoId = $this->matchVideoId($game->name, $videos);

            if ($videoId === null) {
                continue;
            }

            $url = 'https://www.youtube.com/watch?v='.$videoId;

            if ($game->pivot->video_url === $url) {
                continue;
            }

            $list->games()->updateExistingPivot($game->id, ['video_url' => $url, 'video_url_manual' => false]);
            $matched++;
        }

        return ['matched' => $matched, 'scanned' => count($videos)];
    }

    /**
     * @param  list<array{video_id: string, title: string, published_at: mixed}>  $videos
     */
    private function matchVideoId(string $gameName, array $videos): ?string
    {
        $needle = $this->normalize($gameName);

        if ($needle === '') {
            return null;
        }

        // Whole-name phrase match on word boundaries (videos are newest-first → first match wins).
        $needlePadded = ' '.$needle.' ';

        foreach ($videos as $video) {
            if (str_contains(' '.$this->normalize($video['title']).' ', $needlePadded)) {
                return $video['video_id'];
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
