<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_index_requires_authentication(): void
    {
        $response = $this->get('/user/lists');

        $response->assertRedirect('/login');
    }

    public function test_lists_index_displays_user_lists(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/user/lists');

        $response->assertStatus(200);
        $response->assertViewIs('lists.index');
        $response->assertViewHas('regularLists', function ($lists) use ($list) {
            return $lists->contains('id', $list->id);
        });
    }

    public function test_lists_index_creates_special_lists_if_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/user/lists');

        $response->assertStatus(200);
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::BACKLOG->value,
        ]);
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::WISHLIST->value,
        ]);
    }

    public function test_create_list_form_requires_authentication(): void
    {
        $response = $this->get('/user/lists/create');

        $response->assertRedirect('/login');
    }

    public function test_create_list_form_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/user/lists/create');

        $response->assertStatus(200);
        $response->assertViewIs('lists.create');
    }

    public function test_store_creates_new_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'My New List',
            'description' => 'Test description',
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'name' => 'My New List',
            'list_type' => ListTypeEnum::REGULAR->value,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/lists', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_prevents_creating_backlog_wishlist_manually(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'Backlog',
            'list_type' => 'backlog',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_displays_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/user/lists/' . $list->id);

        $response->assertStatus(200);
        $response->assertViewIs('lists.show');
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_requires_authentication(): void
    {
        $list = GameList::factory()->create();

        $response = $this->get('/user/lists/' . $list->id);

        $response->assertRedirect('/login');
    }

    public function test_add_game_to_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);
        $game = Game::factory()->create();

        $response = $this->actingAs($user)->post('/user/lists/' . $list->id . '/games', [
            'game_id' => $game->igdb_id,
        ]);

        $response->assertRedirect();
        $list->refresh();
        $this->assertTrue($list->games()->where('game_id', $game->id)->exists());
    }

    public function test_remove_game_from_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)->delete('/user/lists/' . $list->id . '/games/' . $game->id);

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    public function test_backlog_page_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/backlog');

        $response->assertStatus(200);
        $response->assertViewIs('backlog.index');
    }

    public function test_wishlist_page_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/wishlist');

        $response->assertStatus(200);
        $response->assertViewIs('wishlist.index');
    }

    public function test_public_system_list_is_accessible_without_auth(): void
    {
        $list = GameList::factory()->system()->public()->create([
            'slug' => 'test-list',
            'is_active' => true,
        ]);

        $response = $this->get('/list/test-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_by_slug_shows_public_regular_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
            'slug' => 'my-public-list',
            'is_active' => true, // Visible = is_active = true
            'start_at' => null,
            'end_at' => null,
        ]);

        $response = $this->get('/list/my-public-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_by_slug_returns_404_for_private_list_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        GameList::factory()->create([
            'user_id' => $owner->id,
            'is_public' => false,
            'slug' => 'private-list',
            'is_active' => false, // Not visible (is_active = false)
        ]);

        // Non-owner cannot access inactive private list
        $response = $this->actingAs($otherUser)->get('/list/private-list');
        $response->assertStatus(404);

        // Non-authenticated user cannot access inactive private list
        $response = $this->get('/list/private-list');
        $response->assertStatus(404);
    }

    public function test_show_by_slug_allows_owner_to_access_private_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
            'slug' => 'private-list',
        ]);

        // Owner can access their own private list
        $response = $this->actingAs($user)->get('/list/private-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_by_slug_returns_404_for_inactive_list(): void
    {
        $list = GameList::factory()->system()->public()->create([
            'slug' => 'inactive-list',
            'is_active' => false,
        ]);

        $response = $this->get('/list/inactive-list');

        $response->assertStatus(404);
    }

    public function test_show_by_slug_shows_active_list_regardless_of_date_range(): void
    {
        $list = GameList::factory()->system()->public()->create([
            'slug' => 'expired-list',
            'is_active' => true, // Active lists are visible regardless of date range
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDays(1), // Expired yesterday, but still visible if is_active = true
        ]);

        $response = $this->get('/list/expired-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_by_slug_shows_list_within_date_range(): void
    {
        $list = GameList::factory()->system()->public()->create([
            'slug' => 'active-list',
            'is_active' => true,
            'start_at' => now()->subDays(5),
            'end_at' => now()->addDays(5),
        ]);

        $response = $this->get('/list/active-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_store_generates_slug_for_all_lists(): void
    {
        $user = User::factory()->create();

        // Public list gets slug
        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'My Public List',
            'is_public' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'name' => 'My Public List',
            'is_public' => true,
            'slug' => 'my-public-list',
        ]);

        // Private list also gets slug
        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'My Private List',
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'name' => 'My Private List',
            'is_public' => false,
        ]);
        
        $privateList = GameList::where('name', 'My Private List')->first();
        $this->assertNotNull($privateList->slug);
        $this->assertEquals('my-private-list', $privateList->slug);
    }

    public function test_store_enforces_slug_uniqueness_for_all_lists(): void
    {
        $user = User::factory()->create();
        GameList::factory()->create([
            'slug' => 'existing-slug',
            'is_public' => true,
        ]);

        // Cannot use existing slug for public list
        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'New List',
            'is_public' => true,
            'slug' => 'existing-slug',
        ]);

        $response->assertSessionHasErrors('slug');

        // Cannot use existing slug for private list either
        $response = $this->actingAs($user)->post('/user/lists', [
            'name' => 'Another List',
            'is_public' => false,
            'slug' => 'existing-slug',
        ]);

        $response->assertSessionHasErrors('slug');
    }


    public function test_update_maintains_slug_when_list_becomes_private(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
            'slug' => 'my-test-list',
            'name' => 'My Test List',
        ]);

        $response = $this->actingAs($user)->patch('/user/lists/' . $list->id, [
            'name' => 'My Test List',
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $list->refresh();
        // Slug should still exist even when list becomes private
        $this->assertNotNull($list->slug);
        $this->assertEquals('my-test-list', $list->slug);
    }


    public function test_owner_can_access_inactive_system_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->system()->create([
            'user_id' => $admin->id,
            'slug' => 'inactive-system-list',
            'is_active' => false,
            'is_public' => false,
        ]);

        // Owner can access even if inactive and private
        $response = $this->actingAs($admin)->get('/list/inactive-system-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_update_enforces_slug_uniqueness(): void
    {
        $user = User::factory()->create();
        $existingList = GameList::factory()->create([
            'slug' => 'taken-slug',
            'is_public' => true,
        ]);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
            'slug' => 'my-slug',
        ]);

        $response = $this->actingAs($user)->patch('/user/lists/' . $list->id, [
            'name' => $list->name,
            'is_public' => true,
            'slug' => 'taken-slug',
        ]);

        $response->assertSessionHasErrors('slug');
    }
}
