<?php

namespace App\Http\Controllers;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndieGamesController extends Controller
{
    public function index(Request $request): View
    {
        $year = $request->get('year', now()->year);

        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        $indieList = GameList::where('list_type', ListTypeEnum::INDIE_GAMES->value)
            ->whereBetween('start_at', [$startOfYear, $endOfYear])
            ->where('is_active', true)
            ->where('is_public', true)
            ->with(['games' => function ($query) {
                $query->with(['genres', 'platforms'])
                    ->reorder()
                    ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC');
            }])
            ->first();

        $availableYears = GameList::where('list_type', ListTypeEnum::INDIE_GAMES->value)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNotNull('start_at')
            ->orderByDesc('start_at')
            ->get()
            ->pluck('start_at')
            ->map(fn ($date) => $date->year)
            ->unique()
            ->values();

        $configuredGenres = config('system-lists.indies.genres', []);

        $gamesByGenre = $this->groupGamesByGenre($indieList, $configuredGenres);

        $genreCounts = [];
        foreach ($gamesByGenre as $genreKey => $monthData) {
            $count = 0;
            foreach ($monthData as $month) {
                $count += count($month['games']);
            }
            $genreCounts[$genreKey] = $count;
        }

        $platformEnums = PlatformEnum::getActivePlatforms();

        $defaultGenre = count($configuredGenres) > 0 && isset($gamesByGenre[$configuredGenres[0]])
            ? $configuredGenres[0]
            : (array_key_first($gamesByGenre) ?? 'other');

        return view('indie-games.index', compact(
            'indieList',
            'year',
            'availableYears',
            'configuredGenres',
            'gamesByGenre',
            'genreCounts',
            'platformEnums',
            'defaultGenre'
        ));
    }

    private function groupGamesByGenre(?GameList $list, array $configuredGenres): array
    {
        if (! $list) {
            return [];
        }

        $gamesByGenre = [];

        foreach ($list->games as $game) {
            $indieGenre = $game->pivot->indie_genre ?? null;

            if ($indieGenre && in_array($indieGenre, $configuredGenres)) {
                $genreKey = $indieGenre;
            } else {
                $genreKey = 'other';
            }

            if ($game->pivot->is_tba) {
                $monthKey = 'tba';
                $monthLabel = 'TBA';
            } else {
                $releaseDate = $game->pivot->release_date ?? $game->first_release_date;
                if ($releaseDate && is_string($releaseDate)) {
                    $releaseDate = Carbon::parse($releaseDate);
                }

                $monthKey = $releaseDate ? $releaseDate->format('Y-m') : 'tba';
                $monthLabel = $releaseDate ? $releaseDate->format('F Y') : 'TBA';
            }

            if (! isset($gamesByGenre[$genreKey][$monthKey])) {
                $gamesByGenre[$genreKey][$monthKey] = [
                    'label' => $monthLabel,
                    'games' => [],
                ];
            }

            $gamesByGenre[$genreKey][$monthKey]['games'][] = $game;
        }

        foreach ($gamesByGenre as $genreKey => $months) {
            uksort($gamesByGenre[$genreKey], function ($a, $b) {
                if ($a === 'tba') {
                    return -1;
                }
                if ($b === 'tba') {
                    return 1;
                }

                return $a <=> $b;
            });
        }

        return $gamesByGenre;
    }
}
