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
 * Tests to ensure correct game UUIDs are used when adding games to lists
 * via quick action buttons (backlog/wishlist).
 *
 * These tests cover the bug where clicking on one game's wishlist button
 * would add a different game to the wishlist instead (due to ID collision
 * between database IDs and IGDB IDs).
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
    // Backlog Tests (UUID-based - Quick Actions Components)
    // ============================================================================

    public function test_adding_specific_game_to_backlog_adds_correct_game(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        // Create multiple games to simulate homepage with many games
        $game1 = Game::factory()->create(['name' => 'Game 1']);
        $game2 = Game::factory()->create(['name' => 'Game 2']);
        $game3 = Game::factory()->create(['name' => 'Game 3']);

        // Add game2 specifically using UUID (how quick actions components work)
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $game2->uuid]
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
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        // Create 5 games
        $games = Game::factory()->count(5)->create();

        // Add games in a specific order: 3rd, 1st, 5th
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $games[2]->uuid]
        )->assertStatus(200);

        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $games[0]->uuid]
        )->assertStatus(200);

        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $games[4]->uuid]
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
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

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
    // Wishlist Tests (UUID-based - Quick Actions Components)
    // ============================================================================

    public function test_adding_specific_game_to_wishlist_adds_correct_game(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        // Create multiple games (simulating the bug scenario)
        $game1 = Game::factory()->create(['name' => 'Game A']); // Target game to add
        $game2 = Game::factory()->create(['name' => 'Game B']); // Should NOT be added
        $game3 = Game::factory()->create(['name' => 'Game C']); // Should NOT be added

        // Add game1 specifically using UUID
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => $game1->uuid]
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
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        // Create 10 games to simulate a page with many games
        $games = Game::factory()->count(10)->create();

        // Add specific games: indices 7, 2, 9
        $targetGames = [$games[7], $games[2], $games[9]];

        foreach ($targetGames as $game) {
            $this->actingAs($user)->postJson(
                route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
                ['game_uuid' => $game->uuid]
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
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

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
    // Custom List Tests (UUID-based)
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

        // Add game2 specifically using UUID
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => $customList->slug]),
            ['game_uuid' => $game2->uuid]
        );

        $response->assertStatus(200);

        $customList = $customList->fresh(['games']);
        $this->assertTrue($customList->games->contains('id', $game2->id));
        $this->assertFalse($customList->games->contains('id', $game1->id));
        $this->assertFalse($customList->games->contains('id', $game3->id));
        $this->assertEquals(1, $customList->games->count());
    }

    // ============================================================================
    // UUID Validation Tests
    // ============================================================================

    public function test_cannot_add_game_with_invalid_uuid(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        // Try to add with invalid UUID
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => 'not-a-valid-uuid']
        );

        $response->assertStatus(422); // Validation error

        $wishlist = $wishlist->fresh(['games']);
        $this->assertEquals(0, $wishlist->games->count());
    }

    public function test_cannot_add_game_with_nonexistent_uuid(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        // Try to add with valid UUID format but game doesn't exist
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => '550e8400-e29b-41d4-a716-446655440000']
        );

        $response->assertStatus(404); // Game not found

        $wishlist = $wishlist->fresh(['games']);
        $this->assertEquals(0, $wishlist->games->count());
    }

    // ============================================================================
    // game_id Fallback Tests (For Search/Add Forms)
    // ============================================================================

    public function test_game_id_fallback_finds_game_by_database_id(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        $game = Game::factory()->create(['igdb_id' => 999999]);

        // Using game_id with database ID (for backwards compatibility)
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $game->id]
        );

        $response->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $this->assertTrue($backlog->games->contains('id', $game->id));
    }

    public function test_game_id_fallback_finds_game_by_igdb_id(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        // Create a game with specific IGDB ID
        $game = Game::factory()->create(['igdb_id' => 12345]);

        // Using game_id with IGDB ID (simulating search result)
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => 12345] // IGDB ID
        );

        $response->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $this->assertTrue($backlog->games->contains('id', $game->id));
    }

    public function test_game_id_prioritizes_igdb_id_over_database_id(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        // Create game1 first (it will get database ID 1)
        $game1 = Game::factory()->create(['igdb_id' => 99999]);

        // Create game2 with IGDB ID matching game1's database ID
        $game2 = Game::factory()->create(['igdb_id' => $game1->id]);

        // When using game_id with game1's database ID value,
        // it should find game2 (by IGDB ID) first due to the lookup order
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_id' => $game1->id] // This value is game2's IGDB ID
        );

        $response->assertStatus(200);

        $backlog = $backlog->fresh(['games']);

        // With the fallback logic, IGDB ID is checked first, so game2 gets added
        $this->assertTrue($backlog->games->contains('id', $game2->id), 'Game2 (IGDB match) should be added');
    }

    // ============================================================================
    // Duplicate Prevention Tests
    // ============================================================================

    public function test_adding_same_game_twice_to_backlog_only_adds_once(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        $game = Game::factory()->create();

        // Add game first time
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $game->uuid]
        )->assertStatus(200);

        // Try to add same game again
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $game->uuid]
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
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        $game = Game::factory()->create();

        // Add to backlog only
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $game->uuid]
        )->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $wishlist = $wishlist->fresh(['games']);

        $this->assertTrue($backlog->games->contains('id', $game->id));
        $this->assertFalse($wishlist->games->contains('id', $game->id));
    }

    public function test_adding_game_to_wishlist_does_not_add_to_backlog(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        $game = Game::factory()->create();

        // Add to wishlist only
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => $game->uuid]
        )->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $wishlist = $wishlist->fresh(['games']);

        $this->assertTrue($wishlist->games->contains('id', $game->id));
        $this->assertFalse($backlog->games->contains('id', $game->id));
    }

    public function test_adding_different_games_to_different_lists_maintains_correct_associations(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        $gameForBacklog = Game::factory()->create(['name' => 'Backlog Game']);
        $gameForWishlist = Game::factory()->create(['name' => 'Wishlist Game']);

        // Add different games to different lists
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $gameForBacklog->uuid]
        )->assertStatus(200);

        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => $gameForWishlist->uuid]
        )->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $wishlist = $wishlist->fresh(['games']);

        // Verify correct associations
        $this->assertTrue($backlog->games->contains('id', $gameForBacklog->id));
        $this->assertFalse($backlog->games->contains('id', $gameForWishlist->id));

        $this->assertTrue($wishlist->games->contains('id', $gameForWishlist->id));
        $this->assertFalse($wishlist->games->contains('id', $gameForBacklog->id));
    }

    // ============================================================================
    // UUID Collision Prevention Tests (The Original Bug)
    // ============================================================================

    public function test_uuid_prevents_id_collision_with_igdb_id(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        // Create a game where database ID could potentially match another game's IGDB ID
        $game1 = Game::factory()->create(['name' => 'Target Game', 'igdb_id' => 999999]);

        // Create another game whose IGDB ID equals game1's database ID (the collision scenario)
        $game2 = Game::factory()->create(['name' => 'Colliding Game', 'igdb_id' => $game1->id]);

        // When using UUID, we should always get the exact game we requested
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => $game1->uuid]
        );

        $response->assertStatus(200);

        $wishlist = $wishlist->fresh(['games']);

        // Verify game1 (the target) was added, not game2 (the one with colliding IGDB ID)
        $this->assertTrue($wishlist->games->contains('id', $game1->id), 'Target game should be in wishlist');
        $this->assertFalse($wishlist->games->contains('id', $game2->id), 'Colliding game should NOT be in wishlist');
        $this->assertEquals(1, $wishlist->games->count());
    }

    public function test_uuid_always_adds_correct_game_even_with_matching_ids(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        // Create games where database IDs and IGDB IDs might overlap
        // This simulates a real scenario where game IDs can match IGDB IDs
        $games = [];
        for ($i = 0; $i < 5; $i++) {
            $games[] = Game::factory()->create([
                'name' => "Game {$i}",
                'igdb_id' => 1000 + $i,
            ]);
        }

        // Now create a game whose IGDB ID matches one of the earlier games' database IDs
        $collidingGame = Game::factory()->create([
            'name' => 'Colliding Game',
            'igdb_id' => $games[2]->id, // IGDB ID = database ID of games[2]
        ]);

        // Add games[2] using its UUID - should add games[2], not collidingGame
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $games[2]->uuid]
        );

        $response->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $this->assertTrue($backlog->games->contains('id', $games[2]->id));
        $this->assertFalse($backlog->games->contains('id', $collidingGame->id));
    }

    public function test_multiple_games_with_id_collisions_are_handled_correctly(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $wishlist = GameList::factory()->wishlist()->create(['user_id' => $user->id]);

        // Create first batch of games
        $game1 = Game::factory()->create(['name' => 'First Game', 'igdb_id' => 50000]);
        $game2 = Game::factory()->create(['name' => 'Second Game', 'igdb_id' => 50001]);

        // Create games with IGDB IDs matching the database IDs of game1 and game2
        $collider1 = Game::factory()->create(['name' => 'Collider 1', 'igdb_id' => $game1->id]);
        $collider2 = Game::factory()->create(['name' => 'Collider 2', 'igdb_id' => $game2->id]);

        // Add both original games using their UUIDs
        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => $game1->uuid]
        )->assertStatus(200);

        $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'wishlist']),
            ['game_uuid' => $game2->uuid]
        )->assertStatus(200);

        $wishlist = $wishlist->fresh(['games']);

        // Verify original games were added
        $this->assertTrue($wishlist->games->contains('id', $game1->id));
        $this->assertTrue($wishlist->games->contains('id', $game2->id));

        // Verify colliding games were NOT added
        $this->assertFalse($wishlist->games->contains('id', $collider1->id));
        $this->assertFalse($wishlist->games->contains('id', $collider2->id));

        $this->assertEquals(2, $wishlist->games->count());
    }

    // ============================================================================
    // Game Detail Page Add-to-List Tests (add-to-list.blade.php component)
    // ============================================================================

    public function test_game_detail_page_adds_correct_game_to_backlog(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        // Simulate multiple games existing (like in a real database)
        $otherGame = Game::factory()->create(['name' => 'Other Game', 'igdb_id' => 11111]);
        $targetGame = Game::factory()->create(['name' => 'Target Game', 'igdb_id' => 22222]);

        // Create a game whose IGDB ID matches target's database ID (collision scenario)
        $collidingGame = Game::factory()->create(['name' => 'Colliding Game', 'igdb_id' => $targetGame->id]);

        // Simulate add-to-list component request (uses UUID)
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            ['game_uuid' => $targetGame->uuid]
        );

        $response->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $this->assertTrue($backlog->games->contains('id', $targetGame->id), 'Target game should be added');
        $this->assertFalse($backlog->games->contains('id', $collidingGame->id), 'Colliding game should NOT be added');
        $this->assertFalse($backlog->games->contains('id', $otherGame->id), 'Other game should NOT be added');
    }

    public function test_game_detail_page_adds_to_custom_list_correctly(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $customList = GameList::factory()->create([
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::REGULAR,
            'slug' => 'favorites',
        ]);

        $targetGame = Game::factory()->create(['name' => 'Target Game']);
        $collidingGame = Game::factory()->create(['name' => 'Colliding Game', 'igdb_id' => $targetGame->id]);

        // Simulate add-to-list component request
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'favorites']),
            ['game_uuid' => $targetGame->uuid]
        );

        $response->assertStatus(200);

        $customList = $customList->fresh(['games']);
        $this->assertTrue($customList->games->contains('id', $targetGame->id));
        $this->assertFalse($customList->games->contains('id', $collidingGame->id));
    }

    // ============================================================================
    // UUID Generation Tests
    // ============================================================================

    public function test_games_are_created_with_unique_uuids(): void
    {
        $games = Game::factory()->count(10)->create();

        $uuids = $games->pluck('uuid')->toArray();

        // All UUIDs should be unique
        $this->assertEquals(count($uuids), count(array_unique($uuids)));

        // All UUIDs should be valid format
        foreach ($uuids as $uuid) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $uuid
            );
        }
    }

    public function test_game_uuid_is_immutable_after_creation(): void
    {
        $game = Game::factory()->create();
        $originalUuid = $game->uuid;

        // Update other attributes
        $game->update(['name' => 'Updated Name']);
        $game->refresh();

        $this->assertEquals($originalUuid, $game->uuid);
    }

    // ============================================================================
    // Request Validation Tests
    // ============================================================================

    public function test_request_requires_either_game_uuid_or_game_id(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        GameList::factory()->backlog()->create(['user_id' => $user->id]);

        // Request with neither game_uuid nor game_id
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            []
        );

        $response->assertStatus(422);
    }

    public function test_game_uuid_takes_priority_over_game_id(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $backlog = GameList::factory()->backlog()->create(['user_id' => $user->id]);

        $game1 = Game::factory()->create(['name' => 'Game via UUID']);
        $game2 = Game::factory()->create(['name' => 'Game via ID', 'igdb_id' => 77777]);

        // Send both game_uuid and game_id - UUID should take priority
        $response = $this->actingAs($user)->postJson(
            route('user.lists.games.add', ['user' => $user->username, 'type' => 'backlog']),
            [
                'game_uuid' => $game1->uuid,
                'game_id' => $game2->igdb_id,
            ]
        );

        $response->assertStatus(200);

        $backlog = $backlog->fresh(['games']);
        $this->assertTrue($backlog->games->contains('id', $game1->id), 'Game via UUID should be added');
        $this->assertFalse($backlog->games->contains('id', $game2->id), 'Game via ID should NOT be added');
    }
}
