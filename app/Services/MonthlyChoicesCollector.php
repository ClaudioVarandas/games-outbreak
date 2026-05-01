<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthlyChoicesCollector
{
    public function forCurrentMonth(?CarbonImmutable $now = null, bool $isPreview = false): MonthlyChoicesPayload
    {
        $now ??= CarbonImmutable::now();
        $start = $now->startOfMonth();
        $end = $start->endOfMonth();

        return $this->collect($start, $end, $isPreview);
    }

    public function forUpcomingMonth(?CarbonImmutable $now = null, bool $isPreview = false): MonthlyChoicesPayload
    {
        $now ??= CarbonImmutable::now();
        $start = $now->startOfMonth()->addMonth();
        $end = $start->endOfMonth();

        return $this->collect($start, $end, $isPreview);
    }

    private function collect(CarbonImmutable $start, CarbonImmutable $end, bool $isPreview): MonthlyChoicesPayload
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
                ->limit(40)
                ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
                ->get()
            : new Collection;

        return new MonthlyChoicesPayload(
            windowStart: $start,
            windowEnd: $end,
            games: $games,
            ctaUrl: route('homepage', [], absolute: true),
            isPreview: $isPreview,
        );
    }
}
