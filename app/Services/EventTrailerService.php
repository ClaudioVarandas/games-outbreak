<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EventTrailerService
{
    public function __construct(
        private YoutubeDataService $youtube,
        private IgdbService $igdb,
    ) {}

    /**
     * Resolve each game's pivot video_url to the best available event trailer:
     * the event channel's reveal posted at/after the event start (primary), else
     * the newest IGDB trailer (fallback). Admin-set (manual) trailers are never touched.
     *
     * @return array{matched: int, channel: int, igdb: int, scanned: int}
     */
    public function resolve(GameList $list): array
    {
        $games = $list->games()->get();
        $startAt = $this->eventStartInstant($list);
        $videos = $this->channelVideos($list, $startAt);
        $this->refreshTrailers($games);

        $report = ['matched' => 0, 'channel' => 0, 'igdb' => 0, 'scanned' => count($videos)];

        foreach ($games as $game) {
            if ($game->pivot->video_url_manual) {
                continue;
            }

            $channelVideoId = $this->matchChannelVideo($game->name, $videos, $startAt);
            $source = $channelVideoId !== null ? 'channel' : 'igdb';
            $videoId = $channelVideoId ?? $this->newestIgdbTrailer($game);

            if ($videoId === null) {
                continue;
            }

            $url = 'https://www.youtube.com/watch?v='.$videoId;

            if ($game->pivot->video_url === $url) {
                continue;
            }

            $list->games()->updateExistingPivot($game->id, ['video_url' => $url, 'video_url_manual' => false]);
            $report['matched']++;
            $report[$source]++;
        }

        return $report;
    }

    /**
     * Resolve the event's true start instant. event_data's event_time + event_timezone
     * are the unambiguous source (start_at is stored in the app timezone and can drift by
     * the UTC offset); fall back to start_at only when they are absent.
     */
    private function eventStartInstant(GameList $list): ?Carbon
    {
        return GameList::eventStartAtFor($list->event_data) ?? $list->start_at;
    }

    /**
     * @return list<array{video_id: string, title: string, published_at: ?Carbon}>
     */
    private function channelVideos(GameList $list, ?Carbon $startAt): array
    {
        $channelUrl = $list->event_data['youtube_channel_url'] ?? null;

        if (! $channelUrl) {
            return [];
        }

        try {
            $since = $startAt?->copy()->subHours($this->leadHours());

            return $this->youtube->recentChannelVideos($channelUrl, $since);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Whole-name phrase match within the event window, preferring the earliest upload
     * after the event start (the in-show reveal, not the post-show livestream VOD).
     *
     * @param  list<array{video_id: string, title: string, published_at: ?Carbon}>  $videos
     */
    private function matchChannelVideo(string $gameName, array $videos, ?Carbon $startAt): ?string
    {
        $needle = $this->normalize($gameName);

        if ($needle === '') {
            return null;
        }

        [$lo, $hi] = $this->window($startAt);
        $needlePadded = ' '.$needle.' ';

        $candidates = [];

        foreach ($videos as $video) {
            if (! str_contains(' '.$this->normalize($video['title']).' ', $needlePadded)) {
                continue;
            }

            if ($startAt !== null) {
                $publishedAt = $video['published_at'] ?? null;

                if ($publishedAt === null || $publishedAt->lt($lo) || ($hi !== null && $publishedAt->gt($hi))) {
                    continue;
                }
            }

            $candidates[] = $video;
        }

        if ($candidates === []) {
            return null;
        }

        // Without a start anchor, fall back to the newest match (videos are newest-first).
        if ($startAt === null) {
            return $candidates[0]['video_id'];
        }

        usort($candidates, fn (array $a, array $b): int => $a['published_at'] <=> $b['published_at']);

        return $candidates[0]['video_id'];
    }

    private function newestIgdbTrailer(Game $game): ?string
    {
        $trailers = collect($game->trailers ?? []);

        if ($trailers->isEmpty()) {
            return null;
        }

        $pick = $trailers->contains(fn ($trailer): bool => is_array($trailer) && isset($trailer['id']))
            ? $trailers->sortByDesc(fn (array $trailer) => $trailer['id'] ?? 0)->first()
            : $trailers->last();

        return is_array($pick) && ! empty($pick['video_id']) ? $pick['video_id'] : null;
    }

    /**
     * Refresh the games' stored trailers from IGDB in one batch so reveals added
     * after a game was first imported are available for the fallback.
     *
     * @param  Collection<int, Game>  $games
     */
    private function refreshTrailers(Collection $games): void
    {
        $igdbIds = $games->pluck('igdb_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();

        if ($igdbIds === []) {
            return;
        }

        $videosByGame = $this->igdb->fetchGamesVideos($igdbIds);

        foreach ($games as $game) {
            $fresh = $videosByGame[$game->igdb_id] ?? null;

            if (! empty($fresh) && $fresh !== $game->trailers) {
                $game->trailers = $fresh;
                $game->save();
            }
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function window(?Carbon $startAt): array
    {
        if ($startAt === null) {
            return [null, null];
        }

        $windowHours = (int) config('services.igdb.event_trailer_window_hours', 24);

        return [
            $startAt->copy()->subHours($this->leadHours()),
            $windowHours > 0 ? $startAt->copy()->addHours($windowHours) : null,
        ];
    }

    private function leadHours(): int
    {
        return (int) config('services.igdb.event_trailer_lead_hours', 1);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
