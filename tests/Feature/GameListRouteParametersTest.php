<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests to ensure route parameters are correctly formatted for game list operations.
 *
 * This test suite specifically covers the bug where route('lists.games.remove') was
 * being called with incorrect named parameters instead of positional parameters.
 *
 * Route definition: /list/{type}/{slug}/games/{game}
 * Correct usage: route('lists.games.remove', [$list->list_type->toSlug(), $list->slug, $game])
 * Incorrect usage: route('lists.games.remove', ['gameList' => $list, 'game' => $game])
 */
class GameListRouteParametersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Test that the remove game route works with correct URL format for regular lists
     */
    public function test_remove_game_route_accepts_correct_url_format_for_regular_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-test-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with the correct URL format: /list/{type}/{slug}/games/{game}
        $response = $this->actingAs($user)->delete("/list/regular/my-test-list/games/{$game->id}");

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    /**
     * Test that the remove game route works with correct URL format for backlog lists
     */
    public function test_remove_game_route_accepts_correct_url_format_for_backlog_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::BACKLOG,
            'slug' => 'backlog',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with the correct URL format: /list/backlog/{slug}/games/{game}
        $response = $this->actingAs($user)->delete("/list/backlog/backlog/games/{$game->id}");

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    /**
     * Test that the remove game route works with correct URL format for wishlist lists
     */
    public function test_remove_game_route_accepts_correct_url_format_for_wishlist_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::WISHLIST,
            'slug' => 'wishlist',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with the correct URL format: /list/wishlist/{slug}/games/{game}
        $response = $this->actingAs($user)->delete("/list/wishlist/wishlist/games/{$game->id}");

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    /**
     * Test that the route helper generates correct URL for indie games lists
     */
    public function test_route_helper_generates_correct_url_for_indie_games_list(): void
    {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'slug' => 'indie-spotlight',
        ]);
        $game = Game::factory()->create();

        $url = route('lists.games.remove', [$list->list_type->toSlug(), $list->slug, $game->id]);

        $this->assertEquals(
            url("/list/indie/indie-spotlight/games/{$game->id}"),
            $url
        );
    }

    /**
     * Test that the remove game route works with correct URL format for monthly lists
     */
    public function test_remove_game_route_accepts_correct_url_format_for_monthly_list(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->monthly()->system()->create([
            'user_id' => $user->id,
            'slug' => 'january-2024',
            'is_active' => true,
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with the correct URL format: /list/monthly/{slug}/games/{game}
        $response = $this->actingAs($user)->delete("/list/monthly/january-2024/games/{$game->id}");

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    /**
     * Test that the remove game route works with correct URL format for seasoned lists
     */
    public function test_remove_game_route_accepts_correct_url_format_for_seasoned_list(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->seasoned()->system()->create([
            'user_id' => $user->id,
            'slug' => 'best-of-2024',
            'is_active' => true,
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with the correct URL format: /list/seasoned/{slug}/games/{game}
        $response = $this->actingAs($user)->delete("/list/seasoned/best-of-2024/games/{$game->id}");

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    /**
     * Test that the add game route works with correct URL format
     */
    public function test_add_game_route_accepts_correct_url_format(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-games',
        ]);
        $game = Game::factory()->create();

        // Test with the correct URL format: /list/{type}/{slug}/games
        $response = $this->actingAs($user)->post("/list/regular/my-games/games", [
            'game_id' => $game->igdb_id,
        ]);

        $response->assertRedirect();
        $this->assertTrue($list->fresh()->games->contains($game));
    }

    /**
     * Test that route helper generates correct URLs for regular lists
     */
    public function test_route_helper_generates_correct_url_for_regular_list(): void
    {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);
        $game = Game::factory()->create();

        $url = route('lists.games.remove', [$list->list_type->toSlug(), $list->slug, $game->id]);

        $this->assertEquals(
            url("/list/regular/my-list/games/{$game->id}"),
            $url
        );
    }

    /**
     * Test that route helper generates correct URLs for backlog lists
     */
    public function test_route_helper_generates_correct_url_for_backlog_list(): void
    {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::BACKLOG,
            'slug' => 'backlog',
        ]);
        $game = Game::factory()->create();

        $url = route('lists.games.remove', [$list->list_type->toSlug(), $list->slug, $game->id]);

        $this->assertEquals(
            url("/list/backlog/backlog/games/{$game->id}"),
            $url
        );
    }

    /**
     * Test that route helper generates correct URLs for wishlist lists
     */
    public function test_route_helper_generates_correct_url_for_wishlist_list(): void
    {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::WISHLIST,
            'slug' => 'wishlist',
        ]);
        $game = Game::factory()->create();

        $url = route('lists.games.remove', [$list->list_type->toSlug(), $list->slug, $game->id]);

        $this->assertEquals(
            url("/list/wishlist/wishlist/games/{$game->id}"),
            $url
        );
    }

    /**
     * Test that route helper generates correct URLs for add game route
     */
    public function test_route_helper_generates_correct_url_for_add_game(): void
    {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);

        $url = route('lists.games.add', [$list->list_type->toSlug(), $list->slug]);

        $this->assertEquals(
            url("/list/regular/my-list/games"),
            $url
        );
    }

    /**
     * Test that views can be rendered without route parameter errors for game-quick-actions component
     */
    public function test_game_quick_actions_component_renders_without_route_errors(): void
    {
        $user = User::factory()->create();

        // Create backlog and wishlist
        $backlogList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::BACKLOG,
            'slug' => 'backlog',
        ]);

        $wishlistList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::WISHLIST,
            'slug' => 'wishlist',
        ]);

        $game = Game::factory()->create();

        // Render the component as an authenticated user
        $view = $this->actingAs($user)->blade(
            '<x-game-quick-actions :game="$game" :backlogList="$backlogList" :wishlistList="$wishlistList" />',
            [
                'game' => $game,
                'backlogList' => $backlogList,
                'wishlistList' => $wishlistList,
            ]
        );

        // Should render without errors and contain the expected route URLs
        $view->assertSee('/list/backlog/backlog/games/' . $game->id, false);
        $view->assertSee('/list/wishlist/wishlist/games/' . $game->id, false);
    }

    /**
     * Test that lists.show page renders without errors
     */
    public function test_lists_show_page_renders_without_errors(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'test-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)->get("/list/regular/test-list");

        // Should render successfully without route parameter errors
        $response->assertStatus(200);
    }

    /**
     * Test that lists.edit page renders without errors
     */
    public function test_lists_edit_page_renders_without_errors(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'test-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)->get("/list/regular/test-list/edit");

        // Should render successfully without route parameter errors
        $response->assertStatus(200);
    }

    /**
     * Test that AJAX request to remove game works correctly
     */
    public function test_ajax_remove_game_request_works_correctly(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::BACKLOG,
            'slug' => 'backlog',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Simulate an AJAX request like the ones from game-quick-actions component
        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete("/list/backlog/backlog/games/{$game->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    /**
     * Test that AJAX request to add game works correctly
     */
    public function test_ajax_add_game_request_works_correctly(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::WISHLIST,
            'slug' => 'wishlist',
        ]);
        $game = Game::factory()->create();

        // Simulate an AJAX request like the ones from game-quick-actions component
        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->post("/list/wishlist/wishlist/games", [
                'game_id' => $game->igdb_id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertTrue($list->fresh()->games->contains($game));
    }

    /**
     * Test that incorrect route parameters return 404
     */
    public function test_incorrect_route_parameters_return_404(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with wrong list type
        $response = $this->actingAs($user)->delete("/list/wrong-type/my-list/games/{$game->id}");
        $response->assertStatus(404);

        // Test with wrong slug
        $response = $this->actingAs($user)->delete("/list/regular/wrong-slug/games/{$game->id}");
        $response->assertStatus(404);
    }

    /**
     * Test that route parameters work correctly with special characters in slug
     */
    public function test_route_parameters_work_with_special_characters_in_slug(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-special-list-2024',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)->delete("/list/regular/my-special-list-2024/games/{$game->id}");

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }
}
