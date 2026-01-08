<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests to ensure correct game IDs are used when adding games to lists
 * via quick action buttons (backlog/wishlist).
 *
 * These tests cover the bug where clicking on one game's wishlist button
 * would add a different game to the wishlist instead.
 */
class QuickActionsGameIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        // Mock HTTP calls to prevent IGDB API calls
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
            'api.igdb.com/*' => Http::response([], 200),
            'store.steampowered.com/*' => Http::response([], 200),
        ]);
    }

    // ============================================================================
    // Backlog Tests
    // ============================================================================

    public function test_adding_specific_game_to_backlog_adds_correct_game(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();

        // Create multiple games to simulate homepage with many games
        $game1 = Game::factory()->create(['name' => 'Game 1']);
        $game2 = Game::factory()->create(['name' => 'Game 2']);
        $game3 = Game::factory()->create(['name' => 'Game 3']);

        // Add game2 specifically
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $game2->id]
        );

        $response->assertStatus(200);

        // Verify ONLY game2 was added
        $backlog = $backlog->fresh(['games']);

        $this->assertTrue($backlog->games->contains('id', $game2->id));
        $this->assertFalse($backlog->games->contains('id', $game1->id));
        $this->assertFalse($backlog->games->contains('id', $game3->id));
        $this->assertEquals(1, $backlog->games->count());
    }

    public function test_adding_multiple_games_to_backlog_in_sequence_adds_correct_games(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();

        // Create 5 games
        $games = Game::factory()->count(5)->create();

        // Add games in a specific order: 3rd, 1st, 5th
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $games[2]->id]
        )->assertStatus(200);

        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $games[0]->id]
        )->assertStatus(200);

        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $games[4]->id]
        )->assertStatus(200);

        $backlog = $backlog->fresh(['games']);

        // Verify exactly these 3 games are in the backlog
        $this->assertTrue($backlog->games->contains('id', $games[2]->id));
        $this->assertTrue($backlog->games->contains('id', $games[0]->id));
        $this->assertTrue($backlog->games->contains('id', $games[4]->id));

        // Verify the other games are NOT in the backlog
        $this->assertFalse($backlog->games->contains('id', $games[1]->id));
        $this->assertFalse($backlog->games->contains('id', $games[3]->id));

        $this->assertEquals(3, $backlog->games->count());
    }

    public function test_removing_specific_game_from_backlog_removes_correct_game(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();

        // Create and add multiple games
        $game1 = Game::factory()->create(['name' => 'Game 1']);
        $game2 = Game::factory()->create(['name' => 'Game 2']);
        $game3 = Game::factory()->create(['name' => 'Game 3']);

        $backlog->games()->attach([$game1->id, $game2->id, $game3->id]);
        $this->assertEquals(3, $backlog->fresh()->games->count());

        // Remove game2 specifically
        $response = $this->actingAs($user)->deleteJson(
            route('user.lists.games.remove', ['user' => $user->username, 'type' => 'backlog', 'game' => $game2->id])
        );

        $response->assertStatus(200);

        $backlog = $backlog->fresh(['games']);

        // Verify game2 was removed
        $this->assertFalse($backlog->games->contains('id', $game2->id));

        // Verify game1 and game3 are still there
        $this->assertTrue($backlog->games->contains('id', $game1->id));
        $this->assertTrue($backlog->games->contains('id', $game3->id));
        $this->assertEquals(2, $backlog->games->count());
    }

    // ============================================================================
    // Wishlist Tests
    // ============================================================================

    public function test_adding_specific_game_to_wishlist_adds_correct_game(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = $user->getOrCreateWishlistList();

        // Create multiple games (simulating the bug scenario)
        $game1 = Game::factory()->create(['name' => 'Game A']); // Target game to add
        $game2 = Game::factory()->create(['name' => 'Game B']); // Should NOT be added
        $game3 = Game::factory()->create(['name' => 'Game C']); // Should NOT be added

        // Add game1 specifically
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_id' => $game1->id]
        );

        $response->assertStatus(200);

        // Verify ONLY game1 was added, NOT game2 or game3 (reproducing the bug scenario)
        $wishlist = $wishlist->fresh(['games']);
        $this->assertTrue($wishlist->games->contains('id', $game1->id), "Game {$game1->id} should be in wishlist");
        $this->assertFalse($wishlist->games->contains('id', $game2->id), "Game {$game2->id} should NOT be in wishlist");
        $this->assertFalse($wishlist->games->contains('id', $game3->id), "Game {$game3->id} should NOT be in wishlist");
        $this->assertEquals(1, $wishlist->games->count());
    }

    public function test_adding_multiple_games_to_wishlist_in_sequence_adds_correct_games(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = $user->getOrCreateWishlistList();

        // Create 10 games to simulate a page with many games
        $games = Game::factory()->count(10)->create();

        // Add specific games: indices 7, 2, 9
        $targetGames = [$games[7], $games[2], $games[9]];

        foreach ($targetGames as $game) {
            $this->actingAs($user)->postJson(
                route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
                ['game_id' => $game->id]
            )->assertStatus(200);
        }

        $wishlist = $wishlist->fresh(['games']);

        // Verify exactly these 3 games are in the wishlist
        foreach ($targetGames as $game) {
            $this->assertTrue($wishlist->games->contains('id', $game->id), "Game {$game->id} should be in wishlist");
        }

        // Verify the other games are NOT in the wishlist
        $nonTargetIndices = [0, 1, 3, 4, 5, 6, 8];
        foreach ($nonTargetIndices as $index) {
            $this->assertFalse($wishlist->games->contains('id', $games[$index]->id), "Game {$games[$index]->id} should NOT be in wishlist");
        }

        $this->assertEquals(3, $wishlist->games->count());
    }

    public function test_removing_specific_game_from_wishlist_removes_correct_game(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = $user->getOrCreateWishlistList();

        // Create and add multiple games
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();
        $game3 = Game::factory()->create();

        $wishlist->games()->attach([$game1->id, $game2->id, $game3->id]);

        // Remove game2 specifically
        $response = $this->actingAs($user)->deleteJson(
            route('user.lists.games.remove', ['user' => $user->username, 'type' => 'wishlist', 'game' => $game2->id])
        );

        $response->assertStatus(200);

        $wishlist = $wishlist->fresh(['games']);

        // Verify only game2 was removed
        $this->assertFalse($wishlist->games->contains('id', $game2->id));
        $this->assertTrue($wishlist->games->contains('id', $game1->id));
        $this->assertTrue($wishlist->games->contains('id', $game3->id));
        $this->assertEquals(2, $wishlist->games->count());
    }

    // ============================================================================
    // Custom List Tests
    // ============================================================================

    public function test_adding_specific_game_to_custom_list_adds_correct_game(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $customList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'my-favorites',
        ]);

        // Create multiple games
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();
        $game3 = Game::factory()->create();

        // Add game2 specifically
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => $customList->slug]),
            ['game_id' => $game2->id]
        );

        $response->assertStatus(200);

        $customList = $customList->fresh(['games']);
        $this->assertTrue($customList->games->contains('id', $game2->id));
        $this->assertFalse($customList->games->contains('id', $game1->id));
        $this->assertFalse($customList->games->contains('id', $game3->id));
        $this->assertEquals(1, $customList->games->count());
    }

    // ============================================================================
    // Edge Cases
    // ============================================================================

    public function test_cannot_add_game_with_wrong_id_type(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = $user->getOrCreateWishlistList();

        $game = Game::factory()->create();

        // Try to add with string ID instead of integer
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_id' => 'invalid']
        );

        $response->assertStatus(404); // Game not found

        $wishlist = $wishlist->fresh(['games']);
        $this->assertEquals(0, $wishlist->games->count());
    }

    public function test_adding_same_game_twice_to_backlog_only_adds_once(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();

        $game = Game::factory()->create();

        // Add game first time
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $game->id]
        )->assertStatus(200);

        // Try to add same game again
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $game->id]
        );

        $response->assertStatus(200);
        $response->assertJson(['info' => 'Game is already in this list.']);

        $backlog = $backlog->fresh(['games']);
        $this->assertEquals(1, $backlog->games->count());
    }

    // ============================================================================
    // Cross-List Tests (Ensure Independence)
    // ============================================================================

    public function test_adding_game_to_backlog_does_not_add_to_wishlist(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();
        $wishlist = $user->getOrCreateWishlistList();

        $game = Game::factory()->create();

        // Add to backlog only
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $game->id]
        )->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $wishlist = $wishlist->fresh(['games']);

        $this->assertTrue($backlog->games->contains('id', $game->id));
        $this->assertFalse($wishlist->games->contains('id', $game->id));
    }

    public function test_adding_game_to_wishlist_does_not_add_to_backlog(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();
        $wishlist = $user->getOrCreateWishlistList();

        $game = Game::factory()->create();

        // Add to wishlist only
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_id' => $game->id]
        )->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $wishlist = $wishlist->fresh(['games']);

        $this->assertTrue($wishlist->games->contains('id', $game->id));
        $this->assertFalse($backlog->games->contains('id', $game->id));
    }

    public function test_adding_different_games_to_different_lists_maintains_correct_associations(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = $user->getOrCreateBacklogList();
        $wishlist = $user->getOrCreateWishlistList();

        $gameForBacklog = Game::factory()->create(['name' => 'Backlog Game']);
        $gameForWishlist = Game::factory()->create(['name' => 'Wishlist Game']);

        // Add different games to different lists
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $gameForBacklog->id]
        )->assertStatus(200);

        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_id' => $gameForWishlist->id]
        )->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $wishlist = $wishlist->fresh(['games']);

        // Verify correct associations
        $this->assertTrue($backlog->games->contains('id', $gameForBacklog->id));
        $this->assertFalse($backlog->games->contains('id', $gameForWishlist->id));

        $this->assertTrue($wishlist->games->contains('id', $gameForWishlist->id));
        $this->assertFalse($wishlist->games->contains('id', $gameForBacklog->id));
    }
}
