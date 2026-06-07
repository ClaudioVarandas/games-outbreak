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
     * The single best channel reveal for a game (the bulk auto-matcher's pick).
     *
     * @param  list<array{video_id: string, title: string, published_at: ?Carbon}>  $videos
     */
    private function matchChannelVideo(string $gameName, array $videos, ?Carbon $startAt): ?string
    {
        return $this->matchChannelVideos($gameName, $videos, $startAt)[0]['video_id'] ?? null;
    }

    /**
     * Whole-name phrase matches ordered best-first: when anchored, only videos inside the
     * event window, earliest upload first (the in-show reveal, not the post-show VOD); without
     * an anchor, every title match in channel order (newest-first).
     *
     * @param  list<array{video_id: string, title: string, published_at: ?Carbon}>  $videos
     * @return list<array{video_id: string, title: string, published_at: ?Carbon}>
     */
    private function matchChannelVideos(string $gameName, array $videos, ?Carbon $startAt): array
    {
        $needle = $this->normalize($gameName);

        if ($needle === '') {
            return [];
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

        if ($startAt !== null) {
            usort($candidates, fn (array $a, array $b): int => $a['published_at'] <=> $b['published_at']);
        }

        return $candidates;
    }

    /**
     * All trailer candidates for a single game, for the admin to pick from in the edit modal:
     * the event channel's name-matched uploads first, then the game's IGDB trailers, then —
     * only when those are empty — a general YouTube search. Deduped by video id, channel wins.
     *
     * Unlike the bulk matcher, the channel matches are NOT restricted to the event window: an
     * admin manually searching wants to see every title match, even one posted out of window.
     * The list is enriched with each video's channel name + upload date and sorted newest-first.
     *
     * @return list<array{video_id: string, url: string, title: string, source: string, channel_name: ?string, published_at: ?Carbon, thumbnail_url: string}>
     */
    public function candidates(GameList $list, Game $game): array
    {
        $startAt = $this->eventStartInstant($list);
        $channelVideos = $this->channelVideos($list, $startAt);

        $seen = [];
        $candidates = [];

        foreach ($this->matchChannelVideos($game->name, $channelVideos, null) as $video) {
            if (isset($seen[$video['video_id']])) {
                continue;
            }

            $seen[$video['video_id']] = true;
            $candidates[] = $this->candidate($video['video_id'], $video['title'], 'channel', $video['published_at']);
        }

        foreach ($this->igdbTrailerCandidates($game) as $video) {
            if (isset($seen[$video['video_id']])) {
                continue;
            }

            $seen[$video['video_id']] = true;
            $candidates[] = $this->candidate($video['video_id'], $game->name, 'igdb', null);
        }

        if ($candidates === []) {
            foreach ($this->searchCandidates($game->name) as $video) {
                if (isset($seen[$video['video_id']])) {
                    continue;
                }

                $seen[$video['video_id']] = true;
                $candidates[] = $this->candidate($video['video_id'], $video['title'], 'search', $video['published_at'], $video['thumbnail_url']);
            }
        }

        return $this->enrich($candidates);
    }

    /**
     * Enrich every candidate with its channel name + upload date from one batched videos.list
     * call, then sort newest-first (unknown dates last). Degrades to the unenriched list if the
     * lookup fails.
     *
     * @param  list<array{video_id: string, url: string, title: string, source: string, channel_name: ?string, published_at: ?Carbon, thumbnail_url: string}>  $candidates
     * @return list<array{video_id: string, url: string, title: string, source: string, channel_name: ?string, published_at: ?Carbon, thumbnail_url: string}>
     */
    private function enrich(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        try {
            $meta = $this->youtube->fetchVideos(array_column($candidates, 'video_id'));
        } catch (\Throwable $e) {
            report($e);
            $meta = [];
        }

        foreach ($candidates as &$candidate) {
            $info = $meta[$candidate['video_id']] ?? null;

            if ($info === null) {
                continue;
            }

            $candidate['title'] = $info['title'] ?? $candidate['title'];
            $candidate['channel_name'] = $info['channel_name'] ?? $candidate['channel_name'];
            $candidate['published_at'] = $info['published_at'] ?? $candidate['published_at'];
            $candidate['thumbnail_url'] = $info['thumbnail_url'] ?? $candidate['thumbnail_url'];
        }
        unset($candidate);

        usort($candidates, function (array $a, array $b): int {
            $aTime = $a['published_at']?->getTimestamp();
            $bTime = $b['published_at']?->getTimestamp();

            if ($aTime === $bTime) {
                return 0;
            }

            if ($aTime === null) {
                return 1;
            }

            if ($bTime === null) {
                return -1;
            }

            return $bTime <=> $aTime;
        });

        return $candidates;
    }

    /**
     * The game's stored IGDB trailers, newest (highest id) first. Read-only: unlike the bulk
     * resolve() path this never refreshes from IGDB.
     *
     * @return list<array{video_id: string}>
     */
    private function igdbTrailerCandidates(Game $game): array
    {
        $trailers = collect($game->trailers ?? [])
            ->filter(fn ($trailer): bool => is_array($trailer) && ! empty($trailer['video_id']));

        $ordered = $trailers->contains(fn ($trailer): bool => isset($trailer['id']))
            ? $trailers->sortByDesc(fn (array $trailer) => $trailer['id'] ?? 0)
            : $trailers;

        return $ordered->map(fn (array $trailer): array => ['video_id' => (string) $trailer['video_id']])->values()->all();
    }

    /**
     * @return list<array{video_id: string, title: string, published_at: ?Carbon, thumbnail_url: ?string}>
     */
    private function searchCandidates(string $gameName): array
    {
        try {
            return $this->youtube->searchVideos($gameName.' trailer');
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array{video_id: string, url: string, title: string, source: string, channel_name: ?string, published_at: ?Carbon, thumbnail_url: string}
     */
    private function candidate(string $videoId, string $title, string $source, ?Carbon $publishedAt, ?string $thumbnailUrl = null, ?string $channelName = null): array
    {
        return [
            'video_id' => $videoId,
            'url' => 'https://www.youtube.com/watch?v='.$videoId,
            'title' => $title,
            'source' => $source,
            'channel_name' => $channelName,
            'published_at' => $publishedAt,
            'thumbnail_url' => $thumbnailUrl ?? $this->igdb->getYouTubeThumbnailUrl($videoId),
        ];
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
