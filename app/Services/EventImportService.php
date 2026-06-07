<?php

namespace App\Services;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EventImportService
{
    public function __construct(
        private IgdbService $igdb,
        private GameListPivotSuggester $suggester,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function fetchEvent(int $eventId): ?array
    {
        return $this->igdb->fetchEvent($eventId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchEvents(string $term): array
    {
        return $this->igdb->searchEvents($term);
    }

    /**
     * Find the events list already linked to this IGDB event — by igdb event id,
     * falling back to our generated slug.
     *
     * @param  array<string, mixed>  $event
     */
    public function findExistingList(array $event): ?GameList
    {
        $byId = GameList::events()->where('igdb_event_id', (int) $event['id'])->first();

        if ($byId) {
            return $byId;
        }

        return GameList::events()
            ->where('slug', Str::slug($event['name'] ?? 'Untitled Event'))
            ->first();
    }

    /**
     * Create a new events list or update the existing one. Updates preserve
     * curated fields (name/slug/is_public) and merge event_data.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $overrides
     */
    public function createOrUpdateList(array $event, ?GameList $existing, array $overrides = []): GameList
    {
        $attrs = $this->mapEventToAttributes($event);
        $channelUrl = $this->deriveChannelUrl($event);

        if ($existing === null) {
            $attrs['slug'] = $this->uniqueEventSlug($attrs['slug']);

            if ($channelUrl !== null) {
                $attrs['event_data']['youtube_channel_url'] = $channelUrl;
            }

            return GameList::create(array_replace($attrs, $overrides));
        }

        $existing->igdb_event_id = $attrs['igdb_event_id'];
        $existing->start_at = $attrs['start_at'];
        $existing->end_at = $attrs['end_at'];

        $eventData = array_replace($existing->event_data ?? [], $attrs['event_data']);
        // Only backfill the channel url when the admin hasn't set one — never overwrite it.
        if ($channelUrl !== null && empty($eventData['youtube_channel_url'])) {
            $eventData['youtube_channel_url'] = $channelUrl;
        }
        $existing->event_data = $eventData;

        if (array_key_exists('is_public', $overrides)) {
            $existing->is_public = $overrides['is_public'];
        }

        $existing->save();

        return $existing;
    }

    /**
     * Derive the channel "/videos" URL from the event's YouTube network link.
     *
     * @param  array<string, mixed>  $event
     */
    private function deriveChannelUrl(array $event): ?string
    {
        foreach ($event['event_networks'] ?? [] as $network) {
            $url = $network['url'] ?? null;
            $name = $network['network_type']['name'] ?? '';

            if ($url && (stripos($name, 'youtube') !== false || str_contains($url, 'youtube.com'))) {
                return str_ends_with($url, '/videos') ? $url : rtrim($url, '/').'/videos';
            }
        }

        return null;
    }

    /**
     * Attach any games on the event that are not already in the list. Per-game pivot
     * trailers (video_url) are owned by EventTrailerService, which runs after this and
     * resolves the best channel/IGDB trailer; other pivot fields are left to
     * igdb:gamelist:sync-pivot.
     *
     * @param  array<string, mixed>  $event
     * @return array{added: int, skipped: int, failed: int, refreshed: int, errors: array<int, string>}
     */
    public function syncGames(GameList $list, array $event): array
    {
        $report = ['added' => 0, 'skipped' => 0, 'failed' => 0, 'refreshed' => 0, 'errors' => []];

        $gameIds = array_values(array_unique(array_map('intval', $event['games'] ?? [])));

        if ($gameIds === []) {
            return $report;
        }

        $attachedGameIds = $list->games()->get(['games.id'])->pluck('id')->all();
        $order = (int) $list->games()->max('game_list_game.order');

        foreach ($gameIds as $igdbId) {
            try {
                $game = Game::fetchFromIgdbIfMissing($igdbId, $this->igdb);

                if ($game === null) {
                    $report['failed']++;
                    $report['errors'][$igdbId] = 'IGDB fetch failed';

                    continue;
                }

                // We have IGDB in hand for this event, so keep the game record fresh:
                // refresh an already-known game whose data has gone stale. (Newly-fetched
                // games are current.) Tier-2 pivots of existing games stay with sync-pivot.
                if ($this->gameNeedsRefresh($game)) {
                    $game->refreshFromIgdb($this->igdb);
                    $report['refreshed']++;
                }

                if (in_array($game->id, $attachedGameIds, true)) {
                    $report['skipped']++;

                    continue;
                }

                $game->load(['platforms', 'releaseDates']);
                $platformIds = $game->platforms
                    ->filter(fn (object $platform): bool => PlatformEnum::getActivePlatforms()->has($platform->igdb_id))
                    ->map(fn (object $platform) => $platform->igdb_id)
                    ->values()
                    ->all();

                // Precision-driven release: a known day gives a concrete date; a year
                // without a day gives TBA + year (e.g. "TBA 2027") instead of a fake date.
                $list->games()->attach($game->id, array_merge([
                    'order' => ++$order,
                    'platforms' => json_encode($platformIds),
                ], $this->suggester->releaseSuggestion($game)->toPivot()));

                $attachedGameIds[] = $game->id;
                $report['added']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $report['errors'][$igdbId] = $e->getMessage();
            }
        }

        return $report;
    }

    private function gameNeedsRefresh(Game $game): bool
    {
        $hours = (int) config('services.igdb.event_game_refresh_hours', 24);

        return $game->last_igdb_sync_at === null
            || $game->last_igdb_sync_at->lt(now()->subHours($hours));
    }

    private function uniqueEventSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $counter = 1;

        while (
            GameList::events()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Map a raw IGDB event payload to GameList attributes.
     *
     * The IGDB slug is intentionally ignored — we always generate our own.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public function mapEventToAttributes(array $event): array
    {
        $name = $event['name'] ?? 'Untitled Event';
        $description = $event['description'] ?? null;
        $timezone = $event['time_zone'] ?? 'UTC';

        $startAt = isset($event['start_time'])
            ? Carbon::createFromTimestamp($event['start_time'])
            : null;
        $endAt = isset($event['end_time'])
            ? Carbon::createFromTimestamp($event['end_time'])
            : null;

        $eventData = [
            'event_time' => $startAt
                ? $startAt->copy()->setTimezone($timezone)->format('Y-m-d H:i:s')
                : null,
            'event_timezone' => $timezone,
            'about' => $description,
            'igdb_slug' => $event['slug'] ?? null,
            'video_url' => $this->resolveVideoUrl($event),
            'social_links' => $this->mapSocialLinks($event['event_networks'] ?? []),
        ];

        return [
            'igdb_event_id' => (int) $event['id'],
            'name' => $name,
            'slug' => Str::slug($name),
            'list_type' => ListTypeEnum::EVENTS,
            'is_system' => true,
            'is_active' => true,
            'is_public' => true,
            'user_id' => null,
            // start_at is the queryable UTC mirror of event_data's start instant.
            'start_at' => GameList::eventStartAtFor($eventData),
            'end_at' => $endAt,
            'event_data' => $eventData,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function resolveVideoUrl(array $event): ?string
    {
        if (! empty($event['live_stream_url'])) {
            return $event['live_stream_url'];
        }

        $videoId = $event['videos'][0]['video_id'] ?? null;

        return $videoId ? 'https://www.youtube.com/watch?v='.$videoId : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $networks
     * @return array<int, array{label: string, url: string}>
     */
    private function mapSocialLinks(array $networks): array
    {
        return collect($networks)
            ->filter(fn (array $network): bool => ! empty($network['url']))
            ->map(fn (array $network): array => [
                'label' => $network['network_type']['name'] ?? 'Website',
                'url' => $network['url'],
            ])
            ->values()
            ->all();
    }
}
