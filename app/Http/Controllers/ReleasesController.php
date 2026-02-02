<?php

namespace App\Http\Controllers;

use App\Enums\PlatformEnum;
use App\Models\GameList;
use App\Models\Genre;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReleasesController extends Controller
{
    public function index(Request $request, int $year, ?int $month = null): View
    {
        if ($year < 2020 || $year > 2100) {
            abort(404);
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            abort(404);
        }

        $yearlyList = GameList::yearly()
            ->where('is_system', true)
            ->whereYear('start_at', $year)
            ->with(['games' => function ($query) {
                $query->with(['genres', 'platforms', 'gameModes', 'playerPerspectives'])
                    ->reorder()
                    ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC');
            }])
            ->first();

        // Available years for prev/next navigation
        $availableYears = GameList::yearly()
            ->where('is_system', true)
            ->where('is_active', true)
            ->whereNotNull('start_at')
            ->orderByDesc('start_at')
            ->get()
            ->pluck('start_at')
            ->map(fn ($date) => $date->year)
            ->unique()
            ->values();

        $prevYear = $availableYears->filter(fn ($y) => $y < $year)->first();
        $nextYear = $availableYears->filter(fn ($y) => $y > $year)->sort()->first();

        // Group games by month
        $gamesByMonth = $this->groupGamesByMonth($yearlyList, $month);

        // Get genres for filter
        $genres = Genre::visible()
            ->where('is_pending_review', false)
            ->notOther()
            ->ordered()
            ->get();

        $otherGenre = Genre::where('slug', 'other')->first();

        $platformEnums = PlatformEnum::getActivePlatforms();

        // Prepare JSON data for Alpine.js filtering
        $allGamesJson = $yearlyList ? $yearlyList->getGamesForFiltering() : [];

        return view('releases.yearly', compact(
            'yearlyList',
            'year',
            'month',
            'gamesByMonth',
            'availableYears',
            'prevYear',
            'nextYear',
            'genres',
            'otherGenre',
            'platformEnums',
            'allGamesJson'
        ));
    }

    private function groupGamesByMonth(?GameList $list, ?int $filterMonth = null): array
    {
        if (! $list) {
            return [];
        }

        $gamesByMonth = [];

        foreach ($list->games as $game) {
            if ($game->pivot->is_tba) {
                // Skip TBA games when viewing a single month
                if ($filterMonth !== null) {
                    continue;
                }
                $monthKey = 'tba';
                $monthLabel = 'To Be Announced';
                $monthNumber = null;
            } else {
                $releaseDate = $game->pivot->release_date ?? $game->first_release_date;
                if ($releaseDate && is_string($releaseDate)) {
                    $releaseDate = Carbon::parse($releaseDate);
                }

                if (! $releaseDate) {
                    if ($filterMonth !== null) {
                        continue;
                    }
                    $monthKey = 'tba';
                    $monthLabel = 'To Be Announced';
                    $monthNumber = null;
                } else {
                    $monthNumber = (int) $releaseDate->month;

                    // Filter by month if specified
                    if ($filterMonth !== null && $monthNumber !== $filterMonth) {
                        continue;
                    }

                    $monthKey = $releaseDate->format('Y-m');
                    $monthLabel = $releaseDate->format('F Y');
                }
            }

            if (! isset($gamesByMonth[$monthKey])) {
                $gamesByMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'month_number' => $monthNumber ?? null,
                    'games' => [],
                ];
            }

            $gamesByMonth[$monthKey]['games'][] = $game;
        }

        // Sort: TBA first, then chronological
        uksort($gamesByMonth, function ($a, $b) {
            if ($a === 'tba') {
                return -1;
            }
            if ($b === 'tba') {
                return 1;
            }

            return $a <=> $b;
        });

        return $gamesByMonth;
    }
}
