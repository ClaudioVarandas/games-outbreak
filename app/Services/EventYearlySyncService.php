<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameList;
use App\Support\YouTube;
use Carbon\Carbon;

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
