<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlRestructuringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    // ============================================================================
    // 1. System Lists (Public Routes)
    // ============================================================================

    public function test_monthly_system_lists_are_accessible_via_list_monthly_slug(): void
    {
        $list = GameList::factory()->monthly()->system()->create([
            'slug' => 'january-2026',
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/list/monthly/january-2026');

        $response->assertStatus(200);
        $response->assertViewIs('lists.show');
        $response->assertViewHas('gameList');
        $response->assertViewHas('readOnly', true);
    }

    public function test_indie_system_lists_are_accessible_via_list_indie_slug(): void
    {
        $list = GameList::factory()->indieGames()->system()->create([
            'slug' => 'best-indie-2026',
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/list/indie/best-indie-2026');

        $response->assertStatus(200);
        $response->assertViewIs('lists.show');
        $response->assertViewHas('gameList');
        $response->assertViewHas('readOnly', true);
    }

    public function test_seasoned_system_lists_are_accessible_via_list_seasoned_slug(): void
    {
        $list = GameList::factory()->seasoned()->system()->create([
            'slug' => 'best-horror',
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/list/seasoned/best-horror');

        $response->assertStatus(200);
        $response->assertViewIs('lists.show');
    }

    // ============================================================================
    // 2. User Lists BLOCKED from /list/ Route
    // ============================================================================

    public function test_user_custom_lists_return_404_on_list_regular_slug(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-custom-list',
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/list/regular/my-custom-list');

        $response->assertStatus(404);
    }

    public function test_backlog_type_is_blocked_from_list_route(): void
    {
        $response = $this->get('/list/backlog/some-slug');

        $response->assertStatus(404);
    }

    public function test_wishlist_type_is_blocked_from_list_route(): void
    {
        $response = $this->get('/list/wishlist/some-slug');

        $response->assertStatus(404);
    }

    // ============================================================================
    // 3. User Lists Overview Page
    // ============================================================================

    public function test_user_lists_overview_accessible_at_u_username_lists_as_owner(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();
        $wishlist = $user->getOrCreateWishlistList();
        $customList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
        ]);

        $response = $this->actingAs($user)->get('/u/testuser/lists');

        $response->assertStatus(200);
        $response->assertViewIs('user-lists.my-lists');
        $response->assertViewHas('regularLists');
        $response->assertViewHas('canManage');
    }

    public function test_user_lists_overview_accessible_as_guest_for_public_lists(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $publicList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'is_public' => true,
        ]);

        $response = $this->get('/u/testuser/lists');

        $response->assertStatus(200);
    }

    // ============================================================================
    // 4. User Custom Lists - Dual Mode (View/Edit)
    // ============================================================================

    public function test_public_custom_list_viewable_by_guest_at_u_username_lists_slug(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-custom-list',
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/u/testuser/lists/my-custom-list');

        $response->assertStatus(200);
        $response->assertViewIs('user-lists.lists.show');
        $response->assertViewHas('list', $list);
        $response->assertViewHas('canManage', false);
    }

    public function test_custom_list_shows_management_ui_for_owner(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-custom-list',
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)->get('/u/testuser/lists/my-custom-list');

        $response->assertStatus(200);
        $response->assertViewIs('user-lists.lists.show');
        $response->assertViewHas('list', $list);
        $response->assertViewHas('canManage', true);
        $response->assertSee('(Managing)');
    }

    public function test_private_custom_list_returns_404_for_non_owner(): void
    {
        $owner = User::factory()->create(['username' => 'owner']);
        $otherUser = User::factory()->create();
        $privateList = GameList::factory()->create([
            'user_id' => $owner->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'private-list',
            'is_public' => false,
        ]);

        $response = $this->actingAs($otherUser)->get('/u/owner/lists/private-list');

        $response->assertStatus(404);
    }

    public function test_private_custom_list_returns_404_for_guest(): void
    {
        $owner = User::factory()->create(['username' => 'owner']);
        $privateList = GameList::factory()->create([
            'user_id' => $owner->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'private-list',
            'is_public' => false,
        ]);

        $response = $this->get('/u/owner/lists/private-list');

        $response->assertStatus(404);
    }

    // ============================================================================
    // 5. Create New Custom List
    // ============================================================================

    public function test_create_form_accessible_at_u_username_lists_create(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/u/testuser/lists/create');

        $response->assertStatus(200);
        $response->assertViewIs('user-lists.lists.create');
        $response->assertSee('Create New List');
    }

    public function test_create_form_requires_authentication(): void
    {
        $response = $this->get('/u/someuser/lists/create');

        $response->assertRedirect('/login');
    }

    public function test_create_form_submission_creates_list_and_redirects(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->post('/u/testuser/lists', [
            'name' => 'Test List',
            'description' => 'Testing new URLs',
            'is_public' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'name' => 'Test List',
            'list_type' => 'regular',
        ]);

        $list = GameList::where('user_id', $user->id)
            ->where('name', 'Test List')
            ->first();

        $response->assertRedirect("/u/testuser/lists/{$list->slug}");
    }

    // ============================================================================
    // 6. Update Custom List
    // ============================================================================

    public function test_owner_can_update_custom_list_settings(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($user)->patch('/u/testuser/lists/my-list', [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'is_public' => true,
        ]);

        $response->assertRedirect();
        $list->refresh();
        $this->assertEquals('Updated Name', $list->name);
        $this->assertEquals('Updated description', $list->description);
    }

    public function test_non_owner_cannot_update_custom_list(): void
    {
        $owner = User::factory()->create(['username' => 'owner']);
        $otherUser = User::factory()->create(['username' => 'other']);
        $list = GameList::factory()->create([
            'user_id' => $owner->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);

        $response = $this->actingAs($otherUser)->patch('/u/owner/lists/my-list', [
            'name' => 'Hacked Name',
            'is_public' => true,
        ]);

        $response->assertStatus(403);
        $list->refresh();
        $this->assertNotEquals('Hacked Name', $list->name);
    }

    // ============================================================================
    // 7. Delete Custom List
    // ============================================================================

    public function test_owner_can_delete_custom_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'delete-me',
        ]);

        $response = $this->actingAs($user)->delete('/u/testuser/lists/delete-me');

        $response->assertRedirect('/u/testuser/lists');
        $this->assertDatabaseMissing('game_lists', [
            'id' => $list->id,
        ]);
    }

    public function test_non_owner_cannot_delete_custom_list(): void
    {
        $owner = User::factory()->create(['username' => 'owner']);
        $otherUser = User::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $owner->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);

        $response = $this->actingAs($otherUser)->delete('/u/owner/lists/my-list');

        $response->assertStatus(403);
        $this->assertDatabaseHas('game_lists', [
            'id' => $list->id,
        ]);
    }

    // ============================================================================
    // 8. Add/Remove Games from Custom List
    // ============================================================================

    public function test_owner_can_add_game_to_custom_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $game = Game::factory()->create();
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);

        $gameCountBefore = $list->games()->count();

        $response = $this->actingAs($user)->post('/u/testuser/my-list/games', [
            'game_id' => $game->id,
        ]);

        $response->assertStatus(302);
        $this->assertEquals($gameCountBefore + 1, $list->fresh()->games()->count());
    }

    public function test_owner_can_remove_game_from_custom_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
        ]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)->delete("/u/testuser/my-list/games/{$game->id}");

        $response->assertStatus(302);
        $this->assertDatabaseMissing('game_list_game', [
            'game_list_id' => $list->id,
            'game_id' => $game->id,
        ]);
    }

    public function test_guest_cannot_add_game_to_custom_list(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-list',
            'is_public' => true,
        ]);
        $game = Game::factory()->create();

        $response = $this->post('/u/testuser/my-list/games', [
            'game_id' => $game->id,
        ]);

        $response->assertRedirect('/login');
    }

    // ============================================================================
    // 9. Backlog & Wishlist (Should be Unchanged)
    // ============================================================================

    public function test_backlog_still_accessible_at_u_username_backlog(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();

        $response = $this->actingAs($user)->get('/u/testuser/backlog');

        $response->assertStatus(200);
        $response->assertViewIs('user-lists.backlog');
    }

    public function test_wishlist_still_accessible_at_u_username_wishlist(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = $user->getOrCreateWishlistList();

        $response = $this->actingAs($user)->get('/u/testuser/wishlist');

        $response->assertStatus(200);
        $response->assertViewIs('user-lists.wishlist');
    }

    // ============================================================================
    // 10. Legacy URL Redirects
    // ============================================================================

    public function test_old_u_username_my_lists_redirects_to_u_username_lists(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/u/testuser/my-lists');

        $response->assertStatus(301);
        $response->assertRedirect('/u/testuser/lists');
    }

    public function test_old_u_username_regular_redirects_to_u_username_lists(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/u/testuser/regular');

        $response->assertStatus(301);
        $response->assertRedirect('/u/testuser/lists');
    }

    public function test_old_backlog_redirects_to_user_backlog_after_auth(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/backlog');

        $response->assertStatus(301);
        $response->assertRedirect("/u/testuser/backlog");
    }

    public function test_old_wishlist_redirects_to_user_wishlist_after_auth(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/wishlist');

        $response->assertStatus(301);
        $response->assertRedirect("/u/testuser/wishlist");
    }

    public function test_old_lists_redirects_to_user_lists_after_auth(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/lists');

        $response->assertStatus(301);
        $response->assertRedirect("/u/testuser/lists");
    }

    // ============================================================================
    // 11. Admin Functionality
    // ============================================================================

    public function test_admin_can_access_any_users_private_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'username' => 'admin']);
        $user = User::factory()->create(['username' => 'testuser']);
        $privateList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'private-list',
            'is_public' => false,
        ]);

        $response = $this->actingAs($admin)->get('/u/testuser/lists/private-list');

        $response->assertStatus(200);
        $response->assertViewHas('canManage', true);
    }

    public function test_admin_can_manage_any_users_custom_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'username' => 'admin']);
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'user-list',
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($admin)->patch('/u/testuser/lists/user-list', [
            'name' => 'Admin Updated',
            'is_public' => true,
        ]);

        $response->assertRedirect();
        $list->refresh();
        $this->assertEquals('Admin Updated', $list->name);
    }

    public function test_admin_my_lists_redirects_to_admin_user_lists(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'username' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/my-lists');

        $response->assertStatus(301);
        $response->assertRedirect('/u/admin/lists');
    }

    public function test_admin_user_lists_page_shows_all_users_lists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user1 = User::factory()->create(['username' => 'user1']);
        $user2 = User::factory()->create(['username' => 'user2']);

        $list1 = GameList::factory()->create(['user_id' => $user1->id]);
        $list2 = GameList::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($admin)->get('/admin/user-lists');

        $response->assertStatus(200);
        $response->assertViewIs('admin.user-lists');
        $response->assertSee('user1');
        $response->assertSee('user2');
    }

    // ============================================================================
    // 12. Route Parameter Validation
    // ============================================================================

    public function test_invalid_list_type_returns_404(): void
    {
        $response = $this->get('/list/invalid-type/some-slug');

        $response->assertStatus(404);
    }

    public function test_non_existent_custom_list_returns_404(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/u/testuser/lists/non-existent');

        $response->assertStatus(404);
    }

    public function test_accessing_other_users_custom_list_via_wrong_username_returns_404(): void
    {
        $owner = User::factory()->create(['username' => 'owner']);
        $list = GameList::factory()->create([
            'user_id' => $owner->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'list',
            'is_public' => true,
        ]);

        $response = $this->get('/u/wronguser/lists/list');

        $response->assertStatus(404);
    }

    // ============================================================================
    // 13. URL Format Consistency
    // ============================================================================

    public function test_custom_list_urls_use_kebab_case_slugs(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->post('/u/testuser/lists', [
            'name' => 'My Awesome List',
            'is_public' => true,
        ]);

        $list = GameList::where('user_id', $user->id)
            ->where('name', 'My Awesome List')
            ->first();

        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $list->slug);
        $response->assertRedirect("/u/testuser/lists/{$list->slug}");
    }

    public function test_system_list_type_slugs_are_correct(): void
    {
        // Monthly uses 'monthly'
        $monthly = GameList::factory()->monthly()->system()->create([
            'slug' => 'jan-2026',
            'is_public' => true,
            'is_active' => true,
        ]);
        $response = $this->get('/list/monthly/jan-2026');
        $response->assertStatus(200);

        // Indie uses 'indie' (not 'indie-games')
        $indie = GameList::factory()->indieGames()->system()->create([
            'slug' => 'best-2026',
            'is_public' => true,
            'is_active' => true,
        ]);
        $response = $this->get('/list/indie/best-2026');
        $response->assertStatus(200);

        // Seasoned uses 'seasoned'
        $seasoned = GameList::factory()->seasoned()->system()->create([
            'slug' => 'classics',
            'is_public' => true,
            'is_active' => true,
        ]);
        $response = $this->get('/list/seasoned/classics');
        $response->assertStatus(200);
    }

    // ============================================================================
    // 14. View Variable Consistency
    // ============================================================================

    public function test_game_list_controller_passes_game_list_variable(): void
    {
        $list = GameList::factory()->monthly()->system()->create([
            'slug' => 'test',
            'is_public' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/list/monthly/test');

        $response->assertViewHas('gameList');
        $response->assertViewHas('readOnly', true);
    }

    public function test_user_list_controller_passes_list_variable(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $list = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'test',
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)->get('/u/testuser/lists/test');

        $response->assertViewHas('list');
        $response->assertViewHas('canManage');
    }
}
