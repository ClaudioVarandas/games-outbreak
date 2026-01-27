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
        $activeList = GameList::factory()->system()->monthly()->active()->create([
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

    public function test_old_monthly_releases_redirects(): void
    {
        $response = $this->get('/monthly-releases');

        $response->assertStatus(301);
        $response->assertRedirect('/releases/monthly');
    }

    public function test_releases_monthly_loads(): void
    {
        $response = $this->get('/releases/monthly');

        $response->assertStatus(200);
        $response->assertViewIs('releases.index');
        $response->assertViewHas('type', 'monthly');
    }

    public function test_releases_monthly_displays_active_list_games(): void
    {
        $activeList = GameList::factory()->system()->monthly()->active()->public()->create([
            'start_at' => now()->subDays(1),
            'end_at' => now()->addDays(30),
        ]);

        $game = Game::factory()->create();
        $activeList->games()->attach($game->id);

        $response = $this->get('/releases/monthly');

        $response->assertStatus(200);
        $response->assertViewHas('selectedList', $activeList);
        $response->assertViewHas('selectedList', function ($list) use ($game) {
            return $list && $list->games->contains('id', $game->id);
        });
    }

    public function test_indie_games_page_loads(): void
    {
        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $response->assertViewIs('indie-games.index');
    }

    public function test_old_releases_indie_games_redirects(): void
    {
        $response = $this->get('/releases/indie-games');

        $response->assertStatus(301);
        $response->assertRedirect('/indie-games');
    }

    public function test_releases_seasoned_loads(): void
    {
        $response = $this->get('/releases/seasoned');

        $response->assertStatus(200);
        $response->assertViewIs('releases.index');
        $response->assertViewHas('type', 'seasoned');
    }

    public function test_releases_monthly_navigation(): void
    {
        // Create list for January 2026
        $list = GameList::factory()->system()->monthly()->active()->public()->create([
            'start_at' => \Carbon\Carbon::create(2026, 1, 1),
            'end_at' => \Carbon\Carbon::create(2026, 1, 31),
        ]);

        // Test accessing specific month
        $response = $this->get('/releases/monthly?year=2026&month=1');

        $response->assertStatus(200);
        $response->assertViewHas('year', '2026');
        $response->assertViewHas('month', '1');
        $response->assertViewHas('selectedList', $list);
    }

    public function test_indie_games_year_navigation(): void
    {
        // Create indie-games list for 2026
        $list = GameList::factory()->system()->indieGames()->active()->public()->create([
            'name' => 'Indies 2026',
            'start_at' => \Carbon\Carbon::create(2026, 1, 1),
            'end_at' => \Carbon\Carbon::create(2026, 12, 31),
        ]);

        // Test accessing specific year
        $response = $this->get('/indie-games?year=2026');

        $response->assertStatus(200);
        $response->assertViewHas('year', 2026);
        $response->assertSee('Indies 2026');
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

    public function test_releases_invalid_type_404(): void
    {
        $response = $this->get('/releases/invalid-type');

        $response->assertStatus(404);
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
