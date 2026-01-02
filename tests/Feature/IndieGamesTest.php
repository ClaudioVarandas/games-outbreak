<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
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
        $response->assertViewIs('homepage.indie-games');
    }

    public function test_indie_games_route_accessible_without_auth(): void
    {
        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $this->assertGuest();
    }

    public function test_indie_games_page_displays_active_public_lists(): void
    {
        $activePublic1 = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $activePublic2 = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $inactive = GameList::factory()->indieGames()->create([
            'is_active' => false,
            'is_public' => true,
        ]);

        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $response->assertViewHas('indieGamesLists', function ($lists) use ($activePublic1, $activePublic2, $inactive) {
            return $lists->count() === 2 &&
                   $lists->contains('id', $activePublic1->id) &&
                   $lists->contains('id', $activePublic2->id) &&
                   !$lists->contains('id', $inactive->id);
        });
    }

    public function test_indie_games_page_filters_out_inactive_lists(): void
    {
        $active = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $inactive = GameList::factory()->indieGames()->create([
            'is_active' => false,
            'is_public' => true,
        ]);

        $response = $this->get('/indie-games');

        $response->assertViewHas('indieGamesLists', function ($lists) use ($active, $inactive) {
            return $lists->count() === 1 &&
                   $lists->contains('id', $active->id) &&
                   !$lists->contains('id', $inactive->id);
        });
    }

    public function test_indie_games_page_filters_out_private_lists(): void
    {
        $public = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $private = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => false,
        ]);

        $response = $this->get('/indie-games');

        $response->assertViewHas('indieGamesLists', function ($lists) use ($public, $private) {
            return $lists->count() === 1 &&
                   $lists->contains('id', $public->id) &&
                   !$lists->contains('id', $private->id);
        });
    }

    public function test_indie_games_page_orders_by_created_at_desc(): void
    {
        $oldest = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
            'created_at' => now()->subDays(3),
        ]);
        $newest = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
            'created_at' => now(),
        ]);
        $middle = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->get('/indie-games');

        $response->assertViewHas('indieGamesLists', function ($lists) use ($newest, $middle, $oldest) {
            return $lists->count() === 3 &&
                   $lists->first()->id === $newest->id &&
                   $lists->last()->id === $oldest->id;
        });
    }

    public function test_indie_games_page_loads_with_no_lists(): void
    {
        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $response->assertViewHas('indieGamesLists', function ($lists) {
            return $lists->count() === 0;
        });
    }

    // Admin Form Tests

    public function test_admin_can_create_indie_games_system_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/user/lists', [
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

        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'My Indie Games',
            'is_system' => true,
            'list_type' => 'indie-games',
        ]);

        // Should either be forbidden or create as non-system list
        // Based on authorization logic
        if ($response->status() === 403) {
            $response->assertForbidden();
        } else {
            $this->assertDatabaseMissing('game_lists', [
                'name' => 'My Indie Games',
                'is_system' => true,
            ]);
        }
    }

    public function test_indie_games_list_slug_is_publicly_accessible(): void
    {
        $list = GameList::factory()->indieGames()->create([
            'slug' => 'best-indie-games-2026',
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->get('/list/best-indie-games-2026');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    // Integration Tests

    public function test_indie_games_list_with_games_displays_correctly(): void
    {
        $list = GameList::factory()->indieGames()->create([
            'is_active' => true,
            'is_public' => true,
        ]);

        $games = Game::factory()->count(5)->create();
        foreach ($games as $index => $game) {
            $list->games()->attach($game->id, ['order' => $index]);
        }

        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $response->assertViewHas('indieGamesLists', function ($lists) use ($list) {
            $foundList = $lists->firstWhere('id', $list->id);
            return $foundList && $foundList->games->count() === 5;
        });
    }

    public function test_multiple_indie_games_lists_all_appear_on_page(): void
    {
        $list1 = GameList::factory()->indieGames()->create([
            'name' => 'Indie Platformers',
            'is_active' => true,
            'is_public' => true,
        ]);
        $list2 = GameList::factory()->indieGames()->create([
            'name' => 'Indie RPGs',
            'is_active' => true,
            'is_public' => true,
        ]);
        $list3 = GameList::factory()->indieGames()->create([
            'name' => 'Indie Puzzles',
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->get('/indie-games');

        $response->assertStatus(200);
        $response->assertViewHas('indieGamesLists', function ($lists) {
            return $lists->count() === 3;
        });
        $response->assertSee('Indie Platformers');
        $response->assertSee('Indie RPGs');
        $response->assertSee('Indie Puzzles');
    }
}
