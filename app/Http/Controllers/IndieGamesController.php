<?php

namespace App\Http\Controllers;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Models\GameList;
use App\Models\Genre;
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

        $genres = Genre::visible()
            ->where('is_pending_review', false)
            ->notOther()
            ->ordered()
            ->get();

        $otherGenre = Genre::where('slug', 'other')->first();

        $gamesByMonth = $this->groupGamesByMonth($indieList);

        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('indie-games.index', compact(
            'indieList',
            'year',
            'availableYears',
            'genres',
            'otherGenre',
            'gamesByMonth',
            'platformEnums'
        ));
    }

    private function groupGamesByMonth(?GameList $list): array
    {
        if (! $list) {
            return [];
        }

        $gamesByMonth = [];

        foreach ($list->games as $game) {
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

            if (! isset($gamesByMonth[$monthKey])) {
                $gamesByMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'games' => [],
                ];
            }

            $gamesByMonth[$monthKey]['games'][] = $game;
        }

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
