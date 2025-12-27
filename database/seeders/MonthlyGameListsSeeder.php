<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MonthlyGameListsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startDate = Carbon::create(2026, 1, 1);
        $endDate = Carbon::create(2026, 12, 1);

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $monthName = $current->format('F Y');
            $slug = Str::slug($monthName);

            // Ensure slug is unique
            $originalSlug = $slug;
            $counter = 1;
            while (GameList::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $startAt = $current->copy()->startOfMonth();
            $endAt = $current->copy()->endOfMonth();

            $gameList = GameList::create([
                'user_id' => null,
                'name' => $monthName,
                'description' => "Game releases for {$monthName}",
                'slug' => $slug,
                'is_public' => true,
                'is_system' => true,
                'is_active' => true,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);

            // Get games with release dates within this month
            $gamesInMonth = Game::whereNotNull('first_release_date')
                ->whereBetween('first_release_date', [$startAt, $endAt])
                ->get();

            // If not enough games in this month, get all available games
            $allGames = Game::whereNotNull('first_release_date')->get();

            // Determine how many games to add (between 10 and 30)
            $targetCount = rand(10, 30);

            // Use games from the month if available, otherwise use all games
            $availableGames = $gamesInMonth->count() > 0 ? $gamesInMonth : $allGames;

            // Limit to available games count if less than target
            $gamesToAdd = $availableGames->shuffle()->take(min($targetCount, $availableGames->count()));

            // Attach games to the list with order
            $order = 1;
            foreach ($gamesToAdd as $game) {
                $game->load('platforms');
                $platformIds = $game->platforms
                    ->filter(fn($p) => \App\Enums\PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                    ->map(fn($p) => $p->igdb_id)
                    ->values()
                    ->toArray();
                
                $gameList->games()->attach($game->id, [
                    'order' => $order,
                    'release_date' => $game->first_release_date,
                    'platforms' => json_encode($platformIds),
                ]);
                $order++;
            }

            $current->addMonth();
        }
    }
}
