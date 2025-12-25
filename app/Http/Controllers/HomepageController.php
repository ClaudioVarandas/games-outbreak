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
     * Calculate current week start (Monday) and end (Sunday).
     */
    private function getCurrentWeekRange(): array
    {
        $now = Carbon::now();
        // Start of week is Monday (Carbon::MONDAY = 1)
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return [$weekStart, $weekEnd];
    }

    /**
     * Get all games from games table releasing this week (from today to end of week).
     */
    private function getWeeklyUpcomingGames(): \Illuminate\Support\Collection
    {
        $today = Carbon::today();
        [, $weekEnd] = $this->getCurrentWeekRange();
        
        return Game::whereNotNull('first_release_date')
            ->where('first_release_date', '>=', $today)
            ->where('first_release_date', '<=', $weekEnd)
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
            $today = Carbon::today();
            
            // Get games from the list and filter by release date from today until end of list
            $featuredGames = $activeList->games()
                ->whereNotNull('first_release_date')
                ->where('first_release_date', '>=', $today)
                ->where('first_release_date', '<=', $activeList->end_at)
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

