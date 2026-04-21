<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameList;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WeeklyChoicesCollector
{
    public function forCurrentWeek(?CarbonImmutable $now = null): WeeklyChoicesPayload
    {
        $now ??= CarbonImmutable::now();
        $start = $now->startOfWeek(CarbonInterface::MONDAY);
        $end = $start->endOfWeek(CarbonInterface::SUNDAY);

        return $this->collect($start, $end);
    }

    public function forUpcomingWeek(?CarbonImmutable $now = null): WeeklyChoicesPayload
    {
        $now ??= CarbonImmutable::now();
        $start = $now->startOfWeek(CarbonInterface::MONDAY)->addWeek();
        $end = $start->endOfWeek(CarbonInterface::SUNDAY);

        return $this->collect($start, $end);
    }

    private function collect(CarbonImmutable $start, CarbonImmutable $end): WeeklyChoicesPayload
    {
        $yearlyList = GameList::yearly()
            ->where('is_system', true)
            ->where('is_active', true)
            ->whereYear('start_at', $start->year)
            ->first();

        $games = $yearlyList
            ? $yearlyList->games()
                ->with('platforms')
                ->reorder()
                ->wherePivotBetween('release_date', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->limit(18)
                ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
                ->get()
            : new Collection;

        return new WeeklyChoicesPayload(
            windowStart: $start,
            windowEnd: $end,
            games: $games,
            ctaUrl: route('homepage', [], absolute: true),
        );
    }
}
