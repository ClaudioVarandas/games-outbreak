<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Support\PivotChange;
use App\Support\ReleaseDateSuggestion;
use Carbon\Carbon;

class GameListPivotSuggester
{
    /**
     * Build the set of pivot changes IGDB suggests for a game already in a list.
     * Only fields whose IGDB-derived value differs from the current pivot are
     * returned. Each change carries the partial pivot payload to apply.
     *
     * @return list<PivotChange>
     */
    public function changesFor(Game $game): array
    {
        return array_values(array_filter([
            $this->releaseChange($game),
            $this->earlyAccessChange($game),
            $this->platformsChange($game),
            $this->genresChange($game),
        ]));
    }

    /**
     * Precision-driven release-date suggestion: a known day gives a concrete
     * date; a known year without a day gives TBA + year; less gives plain TBA.
     */
    public function releaseSuggestion(Game $game): ReleaseDateSuggestion
    {
        $primary = $this->primaryReleaseDate($game);

        if ($primary) {
            if ($primary->year && $primary->month && $primary->day) {
                $date = $primary->date ?? Carbon::create($primary->year, $primary->month, $primary->day);

                if ($date) {
                    return ReleaseDateSuggestion::concrete($date instanceof Carbon ? $date : Carbon::parse($date), $primary->human_readable);
                }
            }

            if ($primary->year) {
                return ReleaseDateSuggestion::tba($primary->year, $primary->human_readable);
            }

            return ReleaseDateSuggestion::tba(null, $primary->human_readable);
        }

        if ($game->first_release_date) {
            return ReleaseDateSuggestion::concrete($game->first_release_date->copy());
        }

        return ReleaseDateSuggestion::tba(null);
    }

    private function releaseChange(Game $game): ?PivotChange
    {
        $suggestion = $this->releaseSuggestion($game);
        $pivot = $game->pivot;

        $currentTba = (bool) $pivot->is_tba;
        $currentYear = $pivot->release_year !== null ? (int) $pivot->release_year : null;
        $currentDate = $pivot->release_date ? Carbon::parse($pivot->release_date)->toDateString() : null;

        $differs = $suggestion->isTba
            ? (! $currentTba || $currentYear !== $suggestion->releaseYear || $currentDate !== null)
            : ($currentTba || $currentDate !== $suggestion->releaseDate?->toDateString() || $currentYear !== null);

        if (! $differs) {
            return null;
        }

        $current = $currentTba
            ? 'TBA'.($currentYear ? ' '.$currentYear : '')
            : ($currentDate ? Carbon::parse($currentDate)->format('M j, Y') : '—');

        return new PivotChange('release', 'Release', $current, $suggestion->label(), $suggestion->toPivot());
    }

    private function earlyAccessChange(Game $game): ?PivotChange
    {
        $suggested = $this->primaryReleaseDate($game) !== null
            && $game->releaseDates
                ->where('is_manual', false)
                ->contains(fn (GameReleaseDate $row) => $this->isEarlyAccess($row)
                    && $row->date !== null
                    && $row->date->lte(Carbon::now()));

        // Early Access requires a concrete release date — never suggest it for a TBA pick.
        if ($suggested && $this->releaseSuggestion($game)->isTba) {
            $suggested = false;
        }

        $current = (bool) $game->pivot->is_early_access;

        if ($current === $suggested) {
            return null;
        }

        return new PivotChange(
            'early_access',
            'Early Access',
            $current ? 'Yes' : 'No',
            $suggested ? 'Yes' : 'No',
            ['is_early_access' => $suggested],
        );
    }

    private function platformsChange(Game $game): ?PivotChange
    {
        $suggestedIds = $game->platforms
            ->filter(fn ($platform) => PlatformEnum::getActivePlatforms()->has($platform->igdb_id))
            ->map(fn ($platform) => (int) $platform->igdb_id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $currentIds = collect($this->decodeIds($game->pivot->platforms))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($currentIds === $suggestedIds) {
            return null;
        }

        return new PivotChange(
            'platforms',
            'Platforms',
            $this->platformLabel($currentIds),
            $this->platformLabel($suggestedIds),
            ['platforms' => json_encode($suggestedIds)],
        );
    }

    private function genresChange(Game $game): ?PivotChange
    {
        $suggestedIds = $game->genres
            ->map(fn ($genre) => (int) $genre->id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $currentIds = collect($this->decodeIds($game->pivot->genre_ids))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($currentIds === $suggestedIds) {
            return null;
        }

        $payload = ['genre_ids' => json_encode($suggestedIds)];
        if ($game->pivot->primary_genre_id === null && $suggestedIds !== []) {
            $payload['primary_genre_id'] = $suggestedIds[0];
        }

        return new PivotChange(
            'genres',
            'Genres',
            $this->genreLabel($game, $currentIds),
            $this->genreLabel($game, $suggestedIds),
            $payload,
        );
    }

    private function primaryReleaseDate(Game $game): ?GameReleaseDate
    {
        return $game->releaseDates
            ->where('is_manual', false)
            ->sortBy(fn (GameReleaseDate $row) => $this->sortKey($row))
            ->first();
    }

    private function isEarlyAccess(GameReleaseDate $row): bool
    {
        return str_contains(strtolower($row->status?->name ?? ''), 'early access');
    }

    private function sortKey(GameReleaseDate $row): string
    {
        $year = $row->year ?? $row->date?->year ?? 9999;
        $month = $row->month ?? $row->date?->month ?? 12;
        $day = $row->day ?? $row->date?->day ?? 31;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @param  mixed  $value
     * @return array<int, int|string>
     */
    private function decodeIds($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: [];
        }

        return [];
    }

    /**
     * @param  list<int>  $igdbIds
     */
    private function platformLabel(array $igdbIds): string
    {
        if ($igdbIds === []) {
            return '—';
        }

        return collect($igdbIds)
            ->map(fn (int $id) => PlatformEnum::fromIgdbId($id)?->label() ?? "#{$id}")
            ->implode(', ');
    }

    /**
     * @param  list<int>  $genreIds
     */
    private function genreLabel(Game $game, array $genreIds): string
    {
        if ($genreIds === []) {
            return '—';
        }

        $names = $game->genres->keyBy('id');

        return collect($genreIds)
            ->map(fn (int $id) => $names->get($id)?->name ?? "#{$id}")
            ->implode(', ');
    }
}
