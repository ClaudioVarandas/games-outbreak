<?php

namespace App\Http\Controllers;

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\View\View;

class HomepageController extends Controller
{
    /**
     * Get the active monthly system list for the current month.
     */
    private function getActiveMonthlyList(): ?GameList
    {
        return GameList::monthly()
            ->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->with('games')
            ->first();
    }

    /**
     * Get all active seasoned system lists.
     */
    private function getSeasonedLists(): \Illuminate\Database\Eloquent\Collection
    {
        return GameList::seasoned()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all games from games table releasing this week (from today to end of week).
     */
    private function getWeeklyUpcomingGames(): \Illuminate\Support\Collection
    {
        $today = Carbon::today();

        return Game::whereNotNull('first_release_date')
            ->where('first_release_date', '>=', $today)
            ->with('platforms')
            ->limit(18)
            ->orderBy('first_release_date')
            ->get();
    }

    /**
     * Display the homepage with featured game releases.
     */
    public function index(): View
    {
        $activeList = $this->getActiveMonthlyList();
        $seasonedLists = $this->getSeasonedLists();
        $featuredGames = collect();
        $weeklyUpcomingGames = $this->getWeeklyUpcomingGames();
        $platformEnums = PlatformEnum::getActivePlatforms();

        if ($activeList) {
            // Get all games from the active list (no date filtering)
            // Order by pivot release_date, fallback to game's first_release_date if null
            // Note: We need to override the default orderByPivot('order') from the relationship
            $featuredGames = $activeList->games()
                ->with('platforms')
                ->reorder() // Clear default ordering
                ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
                ->get();
        }

        return view('homepage.index', compact('activeList', 'seasonedLists', 'featuredGames', 'weeklyUpcomingGames', 'platformEnums'));
    }

    /**
     * Display monthly game releases.
     */
    public function monthlyReleases(): View
    {
        $activeList = $this->getActiveMonthlyList();
        $monthGames = collect();
        $platformEnums = PlatformEnum::getActivePlatforms();

        if ($activeList) {
            // Order by pivot release_date, fallback to game's first_release_date if null
            $monthGames = $activeList->games()
                ->with('platforms')
                ->reorder() // Clear default ordering
                ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
                ->get();
        }

        return view('homepage.monthly-releases', compact('activeList', 'monthGames', 'platformEnums'));
    }
}

