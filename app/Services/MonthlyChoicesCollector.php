<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthlyChoicesCollector
{
    private const SAFETY_LIMIT = 200;

    public function forCurrentMonth(?CarbonImmutable $now = null, bool $isPreview = false): MonthlyChoicesPayload
    {
        $now ??= CarbonImmutable::now();

        return $this->collect($now->startOfMonth(), $now, $isPreview);
    }

    public function forUpcomingMonth(?CarbonImmutable $now = null, bool $isPreview = false): MonthlyChoicesPayload
    {
        $now ??= CarbonImmutable::now();

        return $this->collect($now->startOfMonth()->addMonth(), $now, $isPreview);
    }

    public function forMonth(CarbonImmutable $monthStart, ?CarbonImmutable $now = null, bool $isPreview = false): MonthlyChoicesPayload
    {
        $now ??= CarbonImmutable::now();

        return $this->collect($monthStart->startOfMonth(), $now, $isPreview);
    }

    private function collect(CarbonImmutable $start, CarbonImmutable $now, bool $isPreview): MonthlyChoicesPayload
    {
        $end = $start->endOfMonth();

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
                ->limit(self::SAFETY_LIMIT)
                ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
                ->get()
            : new Collection;

        return new MonthlyChoicesPayload(
            windowStart: $start,
            windowEnd: $end,
            games: $games,
            ctaUrl: route('releases.year.month', [
                'year' => $start->year,
                'month' => $start->format('m'),
            ], absolute: true),
            now: $now,
            isPreview: $isPreview,
        );
    }
}
