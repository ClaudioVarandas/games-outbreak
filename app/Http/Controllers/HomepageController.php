<?php

namespace App\Http\Controllers;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
     * Get active event lists with computed status.
     */
    private function getEventBanners(): array
    {
        return GameList::events()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('start_at', 'desc')
            ->get()
            ->map(function (GameList $event) {
                $eventTime = $event->getEventTime();
                $status = $eventTime && $eventTime->isPast() ? 'past' : 'upcoming';

                return [
                    'image' => $event->og_image_path ? asset($event->og_image_path) : '',
                    'link' => route('lists.show', ['type' => ListTypeEnum::EVENTS->value, 'slug' => $event->slug]),
                    'alt' => $event->name,
                    'status' => $status,
                ];
            })
            ->toArray();
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
        $eventBanners = $this->getEventBanners();

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

        return view('homepage.index', compact('activeList', 'seasonedLists', 'featuredGames', 'weeklyUpcomingGames', 'platformEnums', 'eventBanners'));
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

    /**
     * Display indie games lists.
     */
    public function indieGames(): View
    {
        // Get all active indie games system lists
        $indieGamesLists = GameList::indieGames()
            ->where('is_active', true)
            ->where('is_public', true)
            ->with('games.platforms')
            ->orderBy('created_at', 'desc')
            ->get();

        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('homepage.indie-games', compact('indieGamesLists', 'platformEnums'));
    }

    /**
     * Display unified releases page for different list types.
     */
    public function releases(string $type, Request $request): View
    {
        // Get year/month from query params (for monthly type)
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        // Determine list type enum
        $listTypeEnum = match ($type) {
            'monthly' => ListTypeEnum::MONTHLY,
            'indie-games' => ListTypeEnum::INDIE_GAMES,
            'seasoned' => ListTypeEnum::SEASONED,
            default => abort(404)
        };

        // Get lists based on type
        $lists = GameList::where('list_type', $listTypeEnum->value)
            ->where('is_active', true)
            ->where('is_public', true)
            ->with('games.platforms')
            ->get();

        // For monthly and indie-games: filter by year/month based on start_at
        if (in_array($type, ['monthly', 'indie-games'])) {
            $lists = $lists->filter(function ($list) use ($year, $month) {
                return $list->start_at &&
                    $list->start_at->year == $year &&
                    $list->start_at->month == $month;
            });
        }

        // Get the selected list (first active one or from query param)
        $selectedListId = $request->query('list');
        $selectedList = $selectedListId
            ? $lists->firstWhere('id', $selectedListId)
            : $lists->first();

        // Load games with proper ordering if list exists
        if ($selectedList) {
            $selectedList->load(['games' => function ($query) {
                $query->reorder()
                    ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC');
            }]);
        }

        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('releases.index', compact(
            'type',
            'lists',
            'selectedList',
            'platformEnums',
            'year',
            'month'
        ));
    }
}
