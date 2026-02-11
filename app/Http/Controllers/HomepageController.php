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
     * Get games releasing this week from the yearly list.
     */
    private function getThisWeekGames(): \Illuminate\Support\Collection
    {
        $startOfWeek = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $startOfWeek->copy()->endOfWeek(Carbon::SUNDAY);

        $yearlyList = GameList::yearly()
            ->where('is_system', true)
            ->where('is_active', true)
            ->whereYear('start_at', now()->year)
            ->first();

        if (! $yearlyList) {
            return collect();
        }

        return $yearlyList->games()
            ->with('platforms')
            ->reorder()
            ->wherePivotBetween('release_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->limit(12)
            ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC')
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
        $seasonedLists = $this->getSeasonedLists();
        $thisWeekGames = $this->getThisWeekGames();
        $weeklyUpcomingGames = $this->getWeeklyUpcomingGames();
        $platformEnums = PlatformEnum::getActivePlatforms();
        $eventBanners = $this->getEventBanners();

        $currentYear = now()->year;
        $currentMonth = now()->month;

        return view('homepage.index', compact(
            'seasonedLists',
            'thisWeekGames',
            'weeklyUpcomingGames',
            'platformEnums',
            'eventBanners',
            'currentYear',
            'currentMonth'
        ));
    }

    /**
     * Display unified releases page for seasoned type.
     */
    public function releases(string $type, Request $request): View
    {
        if ($type !== 'seasoned') {
            abort(404);
        }

        $lists = GameList::where('list_type', ListTypeEnum::SEASONED->value)
            ->where('is_active', true)
            ->where('is_public', true)
            ->with('games.platforms')
            ->get();

        $selectedListId = $request->query('list');
        $selectedList = $selectedListId
            ? $lists->firstWhere('id', $selectedListId)
            : $lists->first();

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
            'platformEnums'
        ));
    }
}
