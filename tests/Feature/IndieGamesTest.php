<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndieGamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    // Model & Scope Tests

    public function test_scope_indie_games_filters_correctly(): void
    {
        GameList::factory()->create(['list_type' => ListTypeEnum::REGULAR]);
        GameList::factory()->create(['list_type' => ListTypeEnum::MONTHLY]);
        $indieList1 = GameList::factory()->indieGames()->create();
        $indieList2 = GameList::factory()->indieGames()->create();

        $results = GameList::indieGames()->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $indieList1->id));
        $this->assertTrue($results->contains('id', $indieList2->id));
    }

    public function test_is_indie_games_returns_true_for_indie_games_list(): void
    {
        $list = GameList::factory()->indieGames()->create();

        $this->assertTrue($list->isIndieGames());
    }

    public function test_is_indie_games_returns_false_for_other_types(): void
    {
        $regular = GameList::factory()->create(['list_type' => ListTypeEnum::REGULAR]);
        $monthly = GameList::factory()->monthly()->create();
        $seasoned = GameList::factory()->seasoned()->create();

        $this->assertFalse($regular->isIndieGames());
        $this->assertFalse($monthly->isIndieGames());
        $this->assertFalse($seasoned->isIndieGames());
    }

    // Factory Tests

    public function test_factory_indie_games_method_creates_correct_type(): void
    {
        $list = GameList::factory()->indieGames()->create();

        $this->assertEquals(ListTypeEnum::INDIE_GAMES, $list->list_type);
        $this->assertEquals('indie-games', $list->list_type->value);
    }

    // Route & Controller Tests

    public function test_indie_games_route_loads_successfully(): void
    {
        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $response->assertViewIs('indie-games.index');
    }

    public function test_indie_games_route_accessible_without_auth(): void
    {
        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $this->assertGuest();
    }

    public function test_indie_games_page_displays_active_public_lists(): void
    {
        $activePublic = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);
        GameList::factory()->indieGames()->create([
            'is_active' => false,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $response = $this->get('/indie-games?year=2026');

        $response->assertStatus(200);
        $response->assertSee($activePublic->name);
    }

    public function test_indie_games_page_filters_out_inactive_lists(): void
    {
        $active = GameList::factory()->indieGames()->create([
            'name' => 'Active Indies 2026',
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);
        GameList::factory()->indieGames()->create([
            'name' => 'Inactive Indies 2026',
            'is_active' => false,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $response = $this->get('/indie-games?year=2026');

        $response->assertStatus(200);
        $response->assertSee($active->name);
        $response->assertDontSee('Inactive Indies 2026');
    }

    public function test_indie_games_page_filters_out_private_lists(): void
    {
        $public = GameList::factory()->indieGames()->create([
            'name' => 'Public Indies 2026',
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);
        GameList::factory()->indieGames()->create([
            'name' => 'Private Indies 2026',
            'is_active' => true,
            'is_public' => false,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $response = $this->get('/indie-games?year=2026');

        $response->assertStatus(200);
        $response->assertSee($public->name);
        $response->assertDontSee('Private Indies 2026');
    }

    public function test_indie_games_page_loads_with_no_lists(): void
    {
        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $response->assertSee('No active indie games list');
    }

    // Admin Form Tests

    public function test_admin_can_create_indie_games_system_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/system-lists', [
            'name' => 'Best Indie Platformers',
            'description' => 'A curated list of the best indie platformers',
            'is_public' => true,
            'is_system' => true,
            'list_type' => 'indie-games',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'name' => 'Best Indie Platformers',
            'list_type' => 'indie-games',
            'is_system' => true,
        ]);
    }

    public function test_non_admin_cannot_create_system_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->post('/admin/system-lists', [
            'name' => 'My Indie Games',
            'is_system' => true,
            'list_type' => 'indie-games',
        ]);

        // Non-admin users should not be able to access admin routes
        $response->assertForbidden();
        $this->assertDatabaseMissing('game_lists', [
            'name' => 'My Indie Games',
            'is_system' => true,
        ]);
    }

    public function test_indie_games_list_slug_is_publicly_accessible(): void
    {
        $list = GameList::factory()->indieGames()->create([
            'slug' => 'best-indie-games-2026',
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->get('/list/indie/best-indie-games-2026');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    // Integration Tests

    public function test_indie_games_list_with_games_displays_correctly(): void
    {
        $list = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $genre = Genre::factory()->create(['name' => 'Metroidvania', 'slug' => 'metroidvania']);
        $games = Game::factory()->count(5)->create();
        foreach ($games as $index => $game) {
            $list->games()->attach($game->id, [
                'order' => $index,
                'genre_ids' => json_encode([$genre->id]),
                'primary_genre_id' => $genre->id,
            ]);
        }

        $response = $this->get('/indie-games?year=2026');

        $response->assertStatus(200);
        $response->assertSee($list->name);
        foreach ($games as $game) {
            $response->assertSee($game->name);
        }
    }

    public function test_indie_games_year_navigation(): void
    {
        $list2025 = GameList::factory()->indieGames()->create([
            'name' => 'Indies 2025',
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2025-01-01',
            'end_at' => '2025-12-31',
        ]);
        $list2026 = GameList::factory()->indieGames()->create([
            'name' => 'Indies 2026',
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        // Test 2025 shows only 2025 list
        $response1 = $this->get('/indie-games?year=2025');
        $response1->assertStatus(200);
        $response1->assertSee('Indies 2025');
        $response1->assertDontSee('Indies 2026');

        // Test 2026 shows only 2026 list
        $response2 = $this->get('/indie-games?year=2026');
        $response2->assertStatus(200);
        $response2->assertSee('Indies 2026');
        $response2->assertDontSee('Indies 2025');
    }

    public function test_old_indie_games_route_redirects_to_new_route(): void
    {
        $response = $this->get('/releases/indie-games');

        $response->assertRedirect('/indie-games');
        $response->assertStatus(301);
    }
}
