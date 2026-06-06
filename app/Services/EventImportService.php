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
    public function __construct(private IgdbService $igdb) {}

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

        if ($existing === null) {
            $attrs['slug'] = $this->uniqueEventSlug($attrs['slug']);

            return GameList::create(array_replace($attrs, $overrides));
        }

        $existing->igdb_event_id = $attrs['igdb_event_id'];
        $existing->description = $attrs['description'];
        $existing->start_at = $attrs['start_at'];
        $existing->end_at = $attrs['end_at'];
        $existing->event_data = array_replace($existing->event_data ?? [], $attrs['event_data']);

        if (array_key_exists('is_public', $overrides)) {
            $existing->is_public = $overrides['is_public'];
        }

        $existing->save();

        return $existing;
    }

    /**
     * Attach any games on the event that are not already in the list. Existing
     * games are left untouched (pivot refresh is igdb:gamelist:sync-pivot's job).
     *
     * @param  array<string, mixed>  $event
     * @return array{added: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function syncGames(GameList $list, array $event): array
    {
        $report = ['added' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $gameIds = array_values(array_unique(array_map('intval', $event['games'] ?? [])));

        if ($gameIds === []) {
            return $report;
        }

        $attachedGameIds = $list->games()->pluck('games.id')->all();
        $order = (int) $list->games()->max('game_list_game.order');

        foreach ($gameIds as $igdbId) {
            try {
                $game = Game::fetchFromIgdbIfMissing($igdbId, $this->igdb);

                if ($game === null) {
                    $report['failed']++;
                    $report['errors'][$igdbId] = 'IGDB fetch failed';

                    continue;
                }

                if (in_array($game->id, $attachedGameIds, true)) {
                    $report['skipped']++;

                    continue;
                }

                $game->loadMissing('platforms');
                $platformIds = $game->platforms
                    ->filter(fn (object $platform): bool => PlatformEnum::getActivePlatforms()->has($platform->igdb_id))
                    ->map(fn (object $platform) => $platform->igdb_id)
                    ->values()
                    ->all();

                $list->games()->attach($game->id, [
                    'order' => ++$order,
                    'release_date' => $game->first_release_date,
                    'platforms' => json_encode($platformIds),
                ]);

                $attachedGameIds[] = $game->id;
                $report['added']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $report['errors'][$igdbId] = $e->getMessage();
            }
        }

        return $report;
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

        return [
            'igdb_event_id' => (int) $event['id'],
            'name' => $name,
            'description' => $description,
            'slug' => Str::slug($name),
            'list_type' => ListTypeEnum::EVENTS,
            'is_system' => true,
            'is_active' => true,
            'is_public' => true,
            'user_id' => null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'event_data' => [
                'event_time' => $startAt
                    ? $startAt->copy()->setTimezone($timezone)->format('Y-m-d H:i:s')
                    : null,
                'event_timezone' => $timezone,
                'about' => $description,
                'video_url' => $this->resolveVideoUrl($event),
                'social_links' => $this->mapSocialLinks($event['event_networks'] ?? []),
            ],
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
