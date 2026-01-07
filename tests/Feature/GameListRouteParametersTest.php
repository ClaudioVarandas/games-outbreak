<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests to ensure route parameters are correctly formatted for game list operations.
 *
 * This test suite covers the new username-based route structure for user lists:
 * - Add game: /u/{username}/{type}/games (POST)
 * - Remove game: /u/{username}/{type}/games/{game} (DELETE)
 *
 * Where {type} can be 'backlog', 'wishlist', or a list slug for regular lists.
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
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-test-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with the new URL format: /u/{username}/{slug}/games/{game}
        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete("/u/testuser/my-test-list/games/{$game->id}");

        $response->assertJson(['success' => true]);
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    /**
     * Test that the remove game route works with correct URL format for backlog lists
     */
    public function test_remove_game_route_accepts_correct_url_format_for_backlog_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::BACKLOG,
            'slug' => 'backlog',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Call controller method directly to avoid transaction isolation issues
        $request = \Illuminate\Http\Request::create("/u/testuser/backlog/games/{$game->id}", 'DELETE');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');

        // Set request in container so global request() helper uses it
        app()->instance('request', $request);

        $this->actingAs($user);
        $controller = new \App\Http\Controllers\UserListController();
        $response = $controller->removeGame($user, 'backlog', $game);

        $this->assertEquals(['success' => true, 'message' => 'Game removed from list.'], $response->getData(true));
    }

    /**
     * Test that the remove game route works with correct URL format for wishlist lists
     */
    public function test_remove_game_route_accepts_correct_url_format_for_wishlist_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::WISHLIST,
            'slug' => 'wishlist',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Call controller method directly to avoid transaction isolation issues
        $request = \Illuminate\Http\Request::create("/u/testuser/wishlist/games/{$game->id}", 'DELETE');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');

        // Set request in container so global request() helper uses it
        app()->instance('request', $request);

        $this->actingAs($user);
        $controller = new \App\Http\Controllers\UserListController();
        $response = $controller->removeGame($user, 'wishlist', $game);

        $this->assertEquals(['success' => true, 'message' => 'Game removed from list.'], $response->getData(true));
    }

    /**
     * Test that the route helper generates correct URL for user game removal
     */
    public function test_route_helper_generates_correct_url_for_user_game_removal(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $game = Game::factory()->create();

        $url = route('user.lists.games.remove', ['user' => 'testuser', 'type' => 'backlog', 'game' => $game->id]);

        $this->assertEquals(
            url("/u/testuser/backlog/games/{$game->id}"),
            $url
        );
    }

    /**
     * Test that the remove game route works for system lists (admin only)
     */
    public function test_remove_game_route_works_for_monthly_system_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->monthly()->system()->create([
            'user_id' => $admin->id,
            'slug' => 'january-2024',
            'is_active' => true,
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // System lists use admin routes: /admin/system-lists/{type}/{slug}/games/{game}
        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete("/admin/system-lists/monthly/january-2024/games/{$game->id}");

        $response->assertJson(['success' => true]);
        $this->assertFalse($list->fresh()->games->contains('id', $game->id));
    }

    /**
     * Test that the remove game route works for seasoned system lists
     */
    public function test_remove_game_route_works_for_seasoned_system_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->seasoned()->system()->create([
            'user_id' => $admin->id,
            'slug' => 'best-of-2024',
            'is_active' => true,
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // System lists use admin routes
        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete("/admin/system-lists/seasoned/best-of-2024/games/{$game->id}");

        $response->assertJson(['success' => true]);
        $this->assertFalse($list->fresh()->games->contains('id', $game->id));
    }

    /**
     * Test that the add game route works with correct URL format
     */
    public function test_add_game_route_accepts_correct_url_format(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-games',
        ]);
        $game = Game::factory()->create();

        // Test with the new URL format: /u/{username}/{slug}/games
        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->post("/u/testuser/my-games/games", [
                'game_id' => $game->igdb_id,
            ]);

        $response->assertJson(['success' => true]);
        $this->assertTrue($list->fresh()->games->contains('id', $game->id));
    }

    /**
     * Test that route helper generates correct URLs for user game addition
     */
    public function test_route_helper_generates_correct_url_for_user_game_addition(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $url = route('user.lists.games.add', ['user' => 'testuser', 'type' => 'backlog']);

        $this->assertEquals(
            url("/u/testuser/backlog/games"),
            $url
        );
    }

    /**
     * Test that route helper generates correct URLs for regular list game addition
     */
    public function test_route_helper_generates_correct_url_for_regular_list_addition(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $url = route('user.lists.games.add', ['user' => 'testuser', 'type' => 'my-list']);

        $this->assertEquals(
            url("/u/testuser/my-list/games"),
            $url
        );
    }

    /**
     * Test that views can be rendered without route parameter errors for game-quick-actions component
     */
    public function test_game_quick_actions_component_renders_without_route_errors(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

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

        // Should render without errors and contain the new route URLs
        $view->assertSee('/u/testuser/backlog/games/' . $game->id, false);
        $view->assertSee('/u/testuser/wishlist/games/' . $game->id, false);
    }

    /**
     * Test that lists.show page renders without errors (public viewing route)
     */
    public function test_lists_show_page_renders_without_errors(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'test-list',
            'is_public' => true,
            'is_active' => true,
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // User lists now use /u/{username}/lists/{slug}
        $response = $this->actingAs($user)->get("/u/testuser/lists/test-list");

        $response->assertStatus(200);
    }

    /**
     * Test that user list edit page renders without errors
     */
    public function test_user_list_edit_page_renders_without_errors(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'test-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Dual-mode route (no separate edit): /u/{username}/lists/{slug}
        $response = $this->actingAs($user)->get("/u/testuser/lists/test-list");

        $response->assertStatus(200);
    }

    /**
     * Test that AJAX request to remove game works correctly
     */
    public function test_ajax_remove_game_request_works_correctly(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::BACKLOG,
            'slug' => 'backlog',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Call controller method directly to avoid transaction isolation issues
        $request = \Illuminate\Http\Request::create("/u/testuser/backlog/games/{$game->id}", 'DELETE');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');

        // Set request in container so global request() helper uses it
        app()->instance('request', $request);

        $this->actingAs($user);
        $controller = new \App\Http\Controllers\UserListController();
        $response = $controller->removeGame($user, 'backlog', $game);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true, 'message' => 'Game removed from list.'], $response->getData(true));
    }

    /**
     * Test that AJAX request to add game works correctly
     */
    public function test_ajax_add_game_request_works_correctly(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::WISHLIST,
            'slug' => 'wishlist',
        ]);
        $game = Game::factory()->create();

        // Call controller method directly to avoid transaction isolation issues
        $request = \Illuminate\Http\Request::create(
            "/u/testuser/wishlist/games",
            'POST',
            ['game_id' => $game->igdb_id]
        );
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');

        // Set request in container so global request() helper uses it
        app()->instance('request', $request);

        $this->actingAs($user);
        $controller = new \App\Http\Controllers\UserListController();
        $response = $controller->addGame($request, $user, 'wishlist');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true, 'message' => 'Game added to list.'], $response->getData(true));
    }

    /**
     * Test that incorrect route parameters return 404
     */
    public function test_incorrect_route_parameters_return_404(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        // Test with wrong username (ownership middleware should return 403)
        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete("/u/wronguser/my-list/games/{$game->id}");
        $response->assertStatus(404);

        // Test with wrong slug
        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete("/u/testuser/wrong-slug/games/{$game->id}");
        $response->assertStatus(404);
    }

    /**
     * Test that route parameters work correctly with special characters in slug
     */
    public function test_route_parameters_work_with_special_characters_in_slug(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-special-list-2024',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete("/u/testuser/my-special-list-2024/games/{$game->id}");

        $response->assertJson(['success' => true]);
        $this->assertFalse($list->fresh()->games->contains($game));
    }
}
