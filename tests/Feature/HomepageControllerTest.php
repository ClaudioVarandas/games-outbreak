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

    public function test_homepage_displays_this_week_games(): void
    {
        $currentYear = now()->year;

        $yearlyList = GameList::factory()->system()->yearly()->active()->create([
            'start_at' => \Carbon\Carbon::create($currentYear, 1, 1),
            'end_at' => \Carbon\Carbon::create($currentYear, 12, 31),
        ]);

        $game = Game::factory()->create();
        $yearlyList->games()->attach($game->id, [
            'release_date' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('thisWeekGames', function ($games) use ($game) {
            return $games->contains('id', $game->id);
        });
    }

    public function test_homepage_handles_missing_yearly_list(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('thisWeekGames', function ($games) {
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

    public function test_old_monthly_releases_redirects(): void
    {
        $response = $this->get('/monthly-releases');

        $response->assertStatus(301);
        $response->assertRedirect('/releases');
    }

    public function test_old_releases_indie_games_redirects(): void
    {
        $response = $this->get('/releases/indie-games');

        $response->assertStatus(301);
        $response->assertRedirect('/releases');
    }

    public function test_releases_seasoned_loads(): void
    {
        $response = $this->get('/releases/seasoned');

        $response->assertStatus(200);
        $response->assertViewIs('releases.index');
        $response->assertViewHas('type', 'seasoned');
    }

    public function test_releases_list_selection(): void
    {
        // Create two seasoned lists
        $list1 = GameList::factory()->system()->seasoned()->active()->public()->create(['name' => 'Seasoned List 1']);
        $list2 = GameList::factory()->system()->seasoned()->active()->public()->create(['name' => 'Seasoned List 2']);

        // Test selecting specific list
        $response = $this->get('/releases/seasoned?list='.$list2->id);

        $response->assertStatus(200);
        $response->assertViewHas('selectedList', $list2);
    }

    public function test_homepage_shows_past_event_status_for_past_events(): void
    {
        GameList::factory()->system()->events()->public()->create([
            'name' => 'Past Showcase',
            'og_image_path' => '/images/test.webp',
            'event_data' => ['event_time' => now()->subDays(3)->format('Y-m-d\TH:i'), 'event_timezone' => 'UTC'],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('eventBanners', function (array $banners) {
            return count($banners) === 1 && $banners[0]['status'] === 'past';
        });
    }

    public function test_homepage_shows_upcoming_event_status_for_future_events(): void
    {
        GameList::factory()->system()->events()->public()->create([
            'name' => 'Future Showcase',
            'og_image_path' => '/images/test.webp',
            'event_data' => ['event_time' => now()->addDays(3)->format('Y-m-d\TH:i'), 'event_timezone' => 'UTC'],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('eventBanners', function (array $banners) {
            return count($banners) === 1 && $banners[0]['status'] === 'upcoming';
        });
    }

    public function test_homepage_excludes_inactive_events(): void
    {
        GameList::factory()->system()->events()->public()->create([
            'name' => 'Inactive Event',
            'is_active' => false,
            'event_data' => ['event_time' => now()->addDays(3)->format('Y-m-d\TH:i'), 'event_timezone' => 'UTC'],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('eventBanners', function (array $banners) {
            return count($banners) === 0;
        });
    }
}
