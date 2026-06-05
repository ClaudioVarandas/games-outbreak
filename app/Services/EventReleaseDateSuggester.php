<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Support\ReleaseDateSuggestion;
use Carbon\Carbon;

class EventReleaseDateSuggester
{
    /**
     * Derive a pivot release-date suggestion for an event-list game from its
     * IGDB-synced release dates. Precision drives the outcome: a known day gives
     * a concrete date; a known year without a day gives TBA + year; anything
     * less gives a plain TBA.
     */
    public function suggest(Game $game): ReleaseDateSuggestion
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

    private function primaryReleaseDate(Game $game): ?GameReleaseDate
    {
        return $game->releaseDates
            ->where('is_manual', false)
            ->sortBy(fn (GameReleaseDate $row) => $this->sortKey($row))
            ->first();
    }

    private function sortKey(GameReleaseDate $row): string
    {
        $year = $row->year ?? $row->date?->year ?? 9999;
        $month = $row->month ?? $row->date?->month ?? 12;
        $day = $row->day ?? $row->date?->day ?? 31;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
