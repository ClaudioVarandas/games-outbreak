<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_homepage_loads_without_errors(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_view_game_detail_page(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
        ]);

        $response = $this->get('/game/12345');

        $response->assertStatus(200);
        $response->assertViewIs('games.show');
    }

    public function test_user_can_search_for_games(): void
    {
        Game::factory()->create([
            'name' => 'Test Game',
        ]);

        $response = $this->get('/search?q=Test');

        $response->assertStatus(200);
        $response->assertViewIs('search.results');
    }

    public function test_user_can_create_game_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'My List',
            'description' => 'Test',
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'name' => 'My List',
        ]);
    }

    public function test_user_can_add_game_to_backlog(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $response = $this->actingAs($user)->post('/user/lists/' . $user->getOrCreateBacklogList()->id . '/games', [
            'game_id' => $game->igdb_id,
        ]);

        $response->assertRedirect();
        $backlogList = $user->getOrCreateBacklogList();
        $backlogList->refresh();
        $this->assertTrue($backlogList->games()->where('game_id', $game->id)->exists());
    }

    public function test_user_can_add_game_to_wishlist(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $response = $this->actingAs($user)->post('/user/lists/' . $user->getOrCreateWishlistList()->id . '/games', [
            'game_id' => $game->igdb_id,
        ]);

        $response->assertRedirect();
        $wishlistList = $user->getOrCreateWishlistList();
        $wishlistList->refresh();
        $this->assertTrue($wishlistList->games()->where('game_id', $game->id)->exists());
    }

    public function test_user_can_view_their_lists(): void
    {
        $user = User::factory()->create();
        GameList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/user/lists');

        $response->assertStatus(200);
        $response->assertViewIs('lists.index');
    }

    public function test_public_routes_accessible_without_auth(): void
    {
        $this->get('/')->assertStatus(200);
        $this->get('/upcoming')->assertStatus(200);
        $this->get('/most-wanted')->assertStatus(200);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->get('/user/lists')->assertRedirect('/login');
        $this->get('/backlog')->assertRedirect('/login');
        $this->get('/wishlist')->assertRedirect('/login');
    }

    public function test_admin_routes_require_admin_role(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);
        $adminUser = User::factory()->create(['is_admin' => true]);

        $this->actingAs($regularUser)
            ->get('/admin/lists/system')
            ->assertStatus(403);

        $this->actingAs($adminUser)
            ->get('/admin/lists/system')
            ->assertStatus(200);
    }
}
