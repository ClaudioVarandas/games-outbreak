<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameList;
use App\Support\YouTube;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventYearlySyncService
{
    public function __construct(private GameListSyncService $sync) {}

    /**
     * Per-game sync plan for the picker / dry display.
     *
     * @return list<array{game: Game, name: string, release_label: string, target_year: int, has_video: bool, action: string, fills: list<string>}>
     */
    public function plan(GameList $eventList): array
    {
        $eventYear = $eventList->start_at?->year ?? now()->year;
        $plan = [];

        foreach ($eventList->games as $game) {
            $pivot = $game->pivot;
            $isTba = (bool) ($pivot->is_tba ?? false);
            $date = $this->resolveDate($pivot, $game);
            $targetYear = ($isTba || ! $date) ? $eventYear : $date->year;
            $videoUrl = $pivot->video_url ?? null;

            [$action, $fills] = $this->decide($targetYear, $game, $pivot, $date, $videoUrl, $isTba);

            $plan[] = [
                'game' => $game,
                'name' => $game->name,
                'release_label' => ($isTba || ! $date) ? 'TBA' : $date->format('j M Y'),
                'target_year' => $targetYear,
                'has_video' => ! empty(YouTube::idFromUrl($videoUrl)),
                'action' => $action,
                'fills' => $fills,
            ];
        }

        return $plan;
    }

    /**
     * Apply the sync for the chosen game ids. Auto-creates missing yearly lists.
     *
     * @param  list<int>  $gameIds
     * @return array{created_years: list<int>, inserted: int, filled: array<int, list<string>>, skipped: int, errors: array<int, string>, per_year: array<int, int>}
     */
    public function apply(GameList $eventList, array $gameIds): array
    {
        $eventYear = $eventList->start_at?->year ?? now()->year;
        $result = [
            'created_years' => [],
            'inserted' => 0,
            'filled' => [],
            'skipped' => 0,
            'errors' => [],
            'per_year' => [],
        ];

        DB::transaction(function () use ($eventList, $gameIds, $eventYear, &$result) {
            foreach ($eventList->games as $game) {
                if (! in_array($game->id, $gameIds, true)) {
                    continue;
                }

                try {
                    $pivot = $game->pivot;
                    $isTba = (bool) ($pivot->is_tba ?? false);
                    $date = $this->resolveDate($pivot, $game);
                    $targetYear = ($isTba || ! $date) ? $eventYear : $date->year;

                    $existed = $this->sync->findYearlyList($targetYear) !== null;
                    $yearly = $this->sync->firstOrCreateYearlyList($targetYear);
                    if (! $existed) {
                        $result['created_years'][] = $targetYear;
                    }

                    $platforms = $this->sync->resolvePlatforms($game, $pivot->platforms ?? null);
                    $videoUrl = $pivot->video_url ?? null;

                    if ($yearly->games()->where('games.id', $game->id)->exists()) {
                        $filled = $this->sync->fillMissing($yearly, $game, [
                            'release_date' => $isTba ? null : $date,
                            'platforms' => $platforms,
                            'video_url' => $videoUrl,
                        ]);

                        if ($filled) {
                            $result['filled'][$game->id] = $filled;
                        } else {
                            $result['skipped']++;
                        }
                    } else {
                        $this->sync->insertGame($yearly, $game, [
                            'release_date' => $isTba ? null : $date,
                            'platforms' => $platforms,
                            'is_tba' => $isTba,
                            'is_early_access' => (bool) ($pivot->is_early_access ?? false),
                            'is_indie' => false,
                            'is_highlight' => false,
                            'genre_ids' => $this->decodeIntArray($pivot->genre_ids ?? null),
                            'primary_genre_id' => $pivot->primary_genre_id ?? null,
                            'video_url' => $videoUrl,
                        ]);
                        $result['inserted']++;
                    }

                    $result['per_year'][$targetYear] = ($result['per_year'][$targetYear] ?? 0) + 1;
                } catch (\Throwable $e) {
                    // A deadlock / lost connection poisons the surrounding transaction, so let it
                    // abort the run rather than silently misreporting the remaining games as synced.
                    if ($this->isFatalDbError($e)) {
                        throw $e;
                    }

                    $result['errors'][$game->id] = $e->getMessage();
                }
            }
        });

        return $result;
    }

    private function isFatalDbError(\Throwable $e): bool
    {
        foreach (['Deadlock found', 'Lock wait timeout', 'server has gone away', 'database is locked'] as $needle) {
            if (str_contains($e->getMessage(), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function decodeIntArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        return is_array($value) ? array_map('intval', $value) : [];
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function decide(int $targetYear, Game $game, object $pivot, ?Carbon $date, ?string $videoUrl, bool $isTba): array
    {
        $yearly = $this->sync->findYearlyList($targetYear);
        if (! $yearly) {
            return ['insert', []];
        }

        $row = $yearly->games()->where('games.id', $game->id)->first()?->pivot;
        if (! $row) {
            return ['insert', []];
        }

        $fills = [];
        if (empty($row->video_url) && ! empty($videoUrl)) {
            $fills[] = 'video_url';
        }
        if (empty($row->release_date) && ! $isTba && $date) {
            $fills[] = 'release_date';
        }
        $existingPlatforms = $row->platforms;
        if (is_string($existingPlatforms)) {
            $existingPlatforms = json_decode($existingPlatforms, true) ?? [];
        }
        if (empty($existingPlatforms) && ! empty($this->sync->resolvePlatforms($game, $pivot->platforms ?? null))) {
            $fills[] = 'platforms';
        }

        return [$fills ? 'fill' : 'skip', $fills];
    }

    private function resolveDate(object $pivot, Game $game): ?Carbon
    {
        $date = $pivot->release_date ?? null;

        if ($date instanceof Carbon) {
            return $date;
        }
        if (is_string($date) && $date !== '') {
            return Carbon::parse($date);
        }

        return $game->first_release_date;
    }
}
