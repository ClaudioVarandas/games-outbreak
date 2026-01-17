<?php

namespace App\Http\Controllers;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Enums\PlatformGroupEnum;
use App\Models\GameList;
use Illuminate\View\View;

class HighlightsController extends Controller
{
    public function index(): View
    {
        // Get the active highlights list
        $highlightsList = GameList::where('list_type', ListTypeEnum::HIGHLIGHTS->value)
            ->where('is_active', true)
            ->where('is_public', true)
            ->with(['games' => function ($query) {
                $query->with('platforms')
                    ->reorder()
                    ->orderByRaw('COALESCE(game_list_game.release_date, games.first_release_date) ASC');
            }])
            ->first();

        // Group games by platform_group
        $gamesByGroup = [];
        $groupCounts = [];

        if ($highlightsList) {
            foreach ($highlightsList->games as $game) {
                $groupValue = $game->pivot->platform_group ?? PlatformGroupEnum::MULTIPLATFORM->value;
                $gamesByGroup[$groupValue][] = $game;
                $groupCounts[$groupValue] = ($groupCounts[$groupValue] ?? 0) + 1;
            }
        }

        // Get ordered platform groups (only those with games)
        $platformGroups = collect(PlatformGroupEnum::orderedCases())
            ->filter(fn ($group) => ! empty($gamesByGroup[$group->value]))
            ->values();

        // Default to first group with games
        $defaultGroup = $platformGroups->first()?->value ?? PlatformGroupEnum::MULTIPLATFORM->value;

        $platformEnums = PlatformEnum::getActivePlatforms();

        return view('highlights.index', compact(
            'highlightsList',
            'gamesByGroup',
            'groupCounts',
            'platformGroups',
            'defaultGroup',
            'platformEnums'
        ));
    }
}
