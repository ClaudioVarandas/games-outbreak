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

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_lists_index_requires_authentication(): void
    {
        $response = $this->get('/lists');

        $response->assertRedirect('/login');
    }

    public function test_lists_index_displays_user_lists(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create(['user_id' => $user->id]);

        // /lists now redirects to /u/{username}/lists
        $response = $this->actingAs($user)->get('/u/testuser/lists');

        $response->assertViewIs('user-lists.my-lists');
        $response->assertViewHas('regularLists', function ($lists) use ($list) {
            return $lists->contains('id', $list->id);
        });
    }

    public function test_lists_redirect_works(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        // Test that /lists redirects to the new user route
        $response = $this->actingAs($user)->get('/lists');

        $response->assertRedirect(route('user.lists.lists', ['user' => 'testuser']));
    }

    public function test_create_list_form_requires_authentication(): void
    {
        $response = $this->get('/lists/create');

        $response->assertRedirect('/login');
    }

    public function test_create_list_form_loads(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/u/testuser/lists/create');

        $response->assertStatus(200);
        $response->assertViewIs('user-lists.lists.create');
    }

    public function test_store_creates_new_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->post('/u/testuser/lists', [
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
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->post('/u/testuser/lists', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_prevents_creating_backlog_wishlist_manually(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->post('/u/testuser/lists', [
            'name' => 'Backlog',
            'list_type' => 'backlog',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_displays_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->yearly()->system()->create([
            'slug' => 'test-system-list',
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/list/yearly/test-system-list');

        $response->assertStatus(200);
        $response->assertViewIs('lists.show');
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_requires_authentication(): void
    {
        $list = GameList::factory()->create(['is_public' => false]);

        $response = $this->get('/list/'.$list->list_type->toSlug().'/'.$list->slug);

        $response->assertStatus(404); // Private lists return 404 for non-owners
    }

    public function test_add_game_to_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create(['user_id' => $user->id, 'slug' => 'test-list']);
        $game = Game::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->post('/u/testuser/test-list/games', [
                'game_id' => $game->igdb_id,
            ]);

        $response->assertJson(['success' => true]);
        $list->refresh();
        $this->assertTrue($list->games()->where('game_id', $game->id)->exists());
    }

    public function test_remove_game_from_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create(['user_id' => $user->id, 'slug' => 'test-list']);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete('/u/testuser/test-list/games/'.$game->id);

        $response->assertJson(['success' => true]);
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    public function test_backlog_page_redirects_to_user_profile(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/backlog');

        $response->assertRedirect(route('user.lists.backlog', ['user' => 'testuser']));
    }

    public function test_wishlist_page_redirects_to_user_profile(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/wishlist');

        $response->assertRedirect(route('user.lists.wishlist', ['user' => 'testuser']));
    }

    public function test_public_system_list_is_accessible_without_auth(): void
    {
        $list = GameList::factory()->yearly()->system()->public()->create([
            'slug' => 'test-list',
            'is_active' => true,
        ]);

        $response = $this->get('/list/yearly/test-list');

        $response->assertViewHas('gameList', $list);
    }

    public function test_list_route_only_shows_system_lists(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $regularList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-custom-list',
            'is_public' => true,
            'is_active' => true,
        ]);

        // Should return 404 for regular lists on /list/ route
        $response = $this->get('/list/regular/my-custom-list');
        $response->assertStatus(404);

        // Should work on user route
        $response = $this->get("/u/{$user->username}/lists/my-custom-list");
    }

    public function test_show_by_slug_shows_public_regular_list_on_user_route(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
            'slug' => 'my-public-list',
            'is_active' => true,
            'start_at' => null,
            'end_at' => null,
        ]);

        // Should work on user route
        $response = $this->get('/u/testuser/lists/my-public-list');

        $response->assertViewIs('user-lists.lists.show');
        $response->assertViewHas('list', $list);
    }

    public function test_show_by_slug_returns_404_for_private_list_for_non_owner(): void
    {
        $owner = User::factory()->create(['username' => 'owner']);
        $otherUser = User::factory()->create();
        GameList::factory()->create([
            'user_id' => $owner->id,
            'is_public' => false,
            'slug' => 'private-list',
            'is_active' => false,
        ]);

        // Non-owner cannot access private list
        $response = $this->actingAs($otherUser)->get('/u/owner/lists/private-list');
        $response->assertStatus(404);

        // Non-authenticated user cannot access private list
        $response = $this->get('/u/owner/lists/private-list');
        $response->assertStatus(404);
    }

    public function test_show_by_slug_allows_owner_to_access_private_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
            'slug' => 'private-list',
        ]);

        // Owner can access their own private list
        $response = $this->actingAs($user)->get('/u/testuser/lists/private-list');

        $response->assertStatus(200);
        $response->assertViewHas('list', $list);
    }

    public function test_show_by_slug_returns_404_for_inactive_list(): void
    {
        $list = GameList::factory()->yearly()->system()->public()->create([
            'slug' => 'inactive-list',
            'is_active' => false,
        ]);

        $response = $this->get('/list/yearly/inactive-list');

        $response->assertStatus(404);
    }

    public function test_show_by_slug_shows_active_list_regardless_of_date_range(): void
    {
        $list = GameList::factory()->yearly()->system()->public()->create([
            'slug' => 'expired-list',
            'is_active' => true, // Active lists are visible regardless of date range
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDays(1), // Expired yesterday, but still visible if is_active = true
        ]);

        $response = $this->get('/list/yearly/expired-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_by_slug_shows_list_within_date_range(): void
    {
        $list = GameList::factory()->yearly()->system()->public()->create([
            'slug' => 'active-list',
            'is_active' => true,
            'start_at' => now()->subDays(5),
            'end_at' => now()->addDays(5),
        ]);

        $response = $this->get('/list/yearly/active-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_store_generates_slug_for_all_lists(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        // Public list gets slug
        $response = $this->actingAs($user)->post('/u/testuser/lists', [
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
        $response = $this->actingAs($user)->post('/u/testuser/lists', [
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

    public function test_store_enforces_slug_uniqueness_per_list_type(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        // Create a regular list with slug 'my-games'
        GameList::factory()->create([
            'slug' => 'my-games',
            'list_type' => ListTypeEnum::REGULAR,
            'is_public' => true,
        ]);

        // Cannot use same slug for another regular list
        $response = $this->actingAs($user)->post('/u/testuser/lists', [
            'name' => 'New List',
            'is_public' => true,
            'slug' => 'my-games',
        ]);

        $response->assertSessionHasErrors('slug');

        // But CAN use same slug for a different list_type (admin creating system list)
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->post('/admin/system-lists', [
            'name' => 'System List',
            'is_public' => true,
            'is_system' => true,
            'list_type' => 'yearly',
            'slug' => 'my-games', // Same slug, different list_type
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'slug' => 'my-games',
            'list_type' => 'yearly',
        ]);
    }

    public function test_update_maintains_slug_when_list_becomes_private(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
            'slug' => 'my-test-list',
            'name' => 'My Test List',
        ]);

        $response = $this->actingAs($user)->patch('/u/testuser/lists/my-test-list', [
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
        $list = GameList::factory()->yearly()->system()->create([
            'user_id' => $admin->id,
            'slug' => 'inactive-system-list',
            'is_active' => false,
            'is_public' => false,
        ]);

        // Owner can access even if inactive and private
        $response = $this->actingAs($admin)->get('/list/yearly/inactive-system-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList');
    }

    public function test_update_enforces_slug_uniqueness_per_list_type(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        // Create a regular list with slug 'taken-slug'
        $existingList = GameList::factory()->create([
            'slug' => 'taken-slug',
            'list_type' => ListTypeEnum::REGULAR,
            'is_public' => true,
        ]);

        // Create another regular list
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'is_public' => true,
            'slug' => 'my-slug',
        ]);

        // Cannot update to use the same slug within the same list_type
        $response = $this->actingAs($user)->patch('/u/testuser/lists/my-slug', [
            'name' => $list->name,
            'is_public' => true,
            'slug' => 'taken-slug',
        ]);

        $response->assertSessionHasErrors('slug');

        // But CAN use a slug that exists in a different list_type
        $admin = User::factory()->create(['is_admin' => true]);
        $yearlyList = GameList::factory()->yearly()->system()->create([
            'slug' => 'unique-yearly-slug',
        ]);

        // Update yearly list to use a slug that exists in regular lists
        $response = $this->actingAs($admin)->patch('/admin/system-lists/yearly/unique-yearly-slug', [
            'name' => $yearlyList->name,
            'is_public' => true,
            'is_system' => true,
            'list_type' => 'yearly',
            'slug' => 'taken-slug', // Exists in regular, but OK for yearly
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $yearlyList->refresh();
        $this->assertEquals('taken-slug', $yearlyList->slug);
    }

    // === Admin Access Tests ===

    public function test_admin_can_access_any_private_inactive_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['username' => 'owner']);
        $list = GameList::factory()->create([
            'user_id' => $owner->id,
            'slug' => 'private-inactive-list',
            'is_public' => false,
            'is_active' => false,
        ]);

        // Admin can access user lists via /u/{username}/lists/{slug} even if private
        $response = $this->actingAs($admin)->get('/u/owner/lists/private-inactive-list');

        $response->assertStatus(200);
        $response->assertViewHas('list', $list);
    }

    public function test_admin_can_access_inactive_public_system_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->yearly()->system()->create([
            'slug' => 'inactive-public-list',
            'is_public' => true,
            'is_active' => false,
        ]);

        // Admin can access system lists even if inactive
        $response = $this->actingAs($admin)->get('/list/yearly/inactive-public-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }

    public function test_admin_can_access_another_users_private_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['username' => 'owner']);
        $list = GameList::factory()->create([
            'user_id' => $owner->id,
            'slug' => 'other-user-private-list',
            'is_public' => false,
            'is_active' => true,
        ]);

        // Admin can access another user's private list via user route
        $response = $this->actingAs($admin)->get('/u/owner/lists/other-user-private-list');

        $response->assertStatus(200);
        $response->assertViewHas('list', $list);
    }

    public function test_non_admin_cannot_access_inactive_public_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $list = GameList::factory()->yearly()->system()->create([
            'slug' => 'inactive-public-list-non-admin',
            'is_public' => true,
            'is_active' => false,
        ]);

        // Non-admin cannot access inactive list
        $response = $this->actingAs($user)->get('/list/yearly/inactive-public-list-non-admin');

        $response->assertStatus(404);
    }

    public function test_non_admin_cannot_access_other_users_private_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $owner = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $owner->id,
            'slug' => 'other-user-private-non-admin',
            'is_public' => false,
            'is_active' => true,
        ]);

        // Non-admin cannot access another user's private list
        $response = $this->actingAs($user)->get('/u/owner/lists/other-user-private-non-admin');

        $response->assertStatus(404);
    }

    public function test_guest_cannot_access_inactive_public_list(): void
    {
        $list = GameList::factory()->yearly()->system()->create([
            'slug' => 'inactive-public-list-guest',
            'is_public' => true,
            'is_active' => false,
        ]);

        // Guest cannot access inactive list
        $response = $this->get('/list/yearly/inactive-public-list-guest');

        $response->assertStatus(404);
    }

    public function test_guest_cannot_access_private_list(): void
    {
        $owner = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $owner->id,
            'slug' => 'private-list-guest',
            'is_public' => false,
            'is_active' => true,
        ]);

        // Guest cannot access private list
        $response = $this->get('/u/owner/lists/private-list-guest');

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_access_public_active_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->yearly()->system()->create([
            'slug' => 'public-active-list',
            'is_public' => true,
            'is_active' => true,
        ]);

        // Authenticated user can access public active list
        $response = $this->actingAs($user)->get('/list/yearly/public-active-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList');
    }

    public function test_guest_can_access_public_active_list(): void
    {
        $list = GameList::factory()->yearly()->system()->create([
            'slug' => 'public-active-list-guest',
            'is_public' => true,
            'is_active' => true,
        ]);

        // Guest can access public active list
        $response = $this->get('/list/yearly/public-active-list-guest');

        $response->assertStatus(200);
        $response->assertViewHas('gameList');
    }

    public function test_system_lists_dont_appear_in_regular_lists_index(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $regularList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
        ]);
        $systemList = GameList::factory()->yearly()->system()->create([
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/u/testuser/lists');

        $response->assertViewHas('regularLists', function ($lists) use ($regularList, $systemList) {
            return $lists->contains('id', $regularList->id) &&
                   ! $lists->contains('id', $systemList->id);
        });
    }

    public function test_admin_can_update_system_list_with_list_type_field(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->yearly()->system()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        // Update the list - including list_type field (which should be allowed as long as it doesn't change)
        $response = $this->actingAs($admin)->patch('/admin/system-lists/yearly/'.$list->slug, [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'is_public' => true,
            'is_system' => true,
            'list_type' => 'yearly', // Same as current value
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'id' => $list->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'list_type' => 'yearly', // Unchanged
        ]);
    }

    public function test_cannot_change_list_type_when_updating(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = GameList::factory()->yearly()->system()->create([
            'name' => 'Test List',
        ]);

        // Attempt to change list_type from yearly to seasoned
        $response = $this->actingAs($admin)->patch('/admin/system-lists/yearly/'.$list->slug, [
            'name' => 'Test List',
            'is_public' => true,
            'is_system' => true,
            'list_type' => 'seasoned', // Trying to change type
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('list_type');

        // Verify list_type didn't change
        $list->refresh();
        $this->assertEquals('yearly', $list->list_type->value);
    }
}
