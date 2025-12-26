<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('homepage.index');
    }

    public function test_homepage_displays_active_monthly_list(): void
    {
        $activeList = GameList::factory()->system()->active()->create([
            'start_at' => now()->subDays(1),
            'end_at' => now()->addDays(30),
        ]);

        $game = Game::factory()->create();
        $activeList->games()->attach($game->id);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('activeList', $activeList);
        $response->assertViewHas('featuredGames', function ($games) use ($game) {
            return $games->contains('id', $game->id);
        });
    }

    public function test_homepage_handles_missing_active_list(): void
    {
        // No active list created

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('featuredGames', function ($games) {
            return $games->isEmpty();
        });
    }

    public function test_homepage_displays_weekly_upcoming_games(): void
    {
        // Create games within the current week (from today to Sunday)
        $today = \Carbon\Carbon::today();
        $weekEnd = $today->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->endOfDay();
        
        // Ensure dates are in the future relative to today, but within the current week
        $thisWeek = $today->copy()->addDays(1); // Tomorrow
        $nextWeek = $weekEnd->copy()->subDays(1); // Saturday (or earlier if weekEnd is today)

        // Make sure dates are valid (not in the past and within week)
        if ($thisWeek->isPast()) {
            $thisWeek = $today->copy()->addDays(1);
        }
        if ($nextWeek->isPast() || $nextWeek->gt($weekEnd)) {
            $nextWeek = $weekEnd->copy()->subDays(1);
        }
        if ($nextWeek->isPast()) {
            $nextWeek = $today->copy()->addDays(1);
        }

        $thisWeekGame = Game::factory()->create([
            'first_release_date' => $thisWeek,
        ]);

        $nextWeekGame = Game::factory()->create([
            'first_release_date' => $nextWeek,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('weeklyUpcomingGames', function ($games) use ($thisWeekGame, $nextWeekGame) {
            return $games->contains('id', $thisWeekGame->id) &&
                   $games->contains('id', $nextWeekGame->id);
        });
    }

    public function test_monthly_releases_page_loads(): void
    {
        $response = $this->get('/monthly-releases');

        $response->assertStatus(200);
        $response->assertViewIs('homepage.monthly-releases');
    }

    public function test_monthly_releases_displays_active_list_games(): void
    {
        $activeList = GameList::factory()->system()->active()->create([
            'start_at' => now()->subDays(1),
            'end_at' => now()->addDays(30),
        ]);

        $game = Game::factory()->create();
        $activeList->games()->attach($game->id);

        $response = $this->get('/monthly-releases');

        $response->assertStatus(200);
        $response->assertViewHas('activeList', $activeList);
        $response->assertViewHas('monthGames', function ($games) use ($game) {
            return $games->contains('id', $game->id);
        });
    }
}
