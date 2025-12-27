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
     * Get the active system list for the current month.
     */
    private function getActiveMonthlyList(): ?GameList
    {
        return GameList::where('is_system', true)
            ->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->with('games')
            ->first();
    }

    /**
     * Get all games from games table releasing this week (from today to end of week).
     */
    private function getWeeklyUpcomingGames(): \Illuminate\Support\Collection
    {
        $today = Carbon::today();
        $endDate = $today->copy()->addWeek();

        return Game::whereNotNull('first_release_date')
            ->where('first_release_date', '>=', $today)
            ->where('first_release_date', '<=', $endDate)
            ->with('platforms')
            ->orderBy('first_release_date')
            ->get();
    }

    /**
     * Display the homepage with featured game releases.
     */
    public function index(): View
    {
        $activeList = $this->getActiveMonthlyList();
        $featuredGames = collect();
        $weeklyUpcomingGames = $this->getWeeklyUpcomingGames();
        $platformEnums = PlatformEnum::getActivePlatforms();

        if ($activeList) {
            // Get all games from the active list (no date filtering)
            $featuredGames = $activeList->games()
                ->with('platforms')
                ->orderBy('first_release_date')
                ->get();
        }

        return view('homepage.index', compact('activeList', 'featuredGames', 'weeklyUpcomingGames', 'platformEnums'));
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
            $monthGames = $activeList->games()
                ->with('platforms')
                ->orderBy('first_release_date')
                ->get();
        }

        return view('homepage.monthly-releases', compact('activeList', 'monthGames', 'platformEnums'));
    }
}

