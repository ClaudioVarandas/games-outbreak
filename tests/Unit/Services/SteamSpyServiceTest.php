<?php

namespace Tests\Unit\Services;

use App\Models\ExternalGameSource;
use App\Models\Game;
use App\Models\GameExternalSource;
use App\Models\SteamGameData;
use App\Services\SteamSpyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteamSpyServiceTest extends TestCase
{
    use RefreshDatabase;

    private SteamSpyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SteamSpyService;
    }

    public function test_fetch_game_details_returns_data_on_success(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([
                'appid' => 123456,
                'name' => 'Test Game',
                'owners' => '1,000,000 .. 2,000,000',
                'average_forever' => 120,
                'ccu' => 5000,
            ], 200),
        ]);

        $data = $this->service->fetchGameDetails('123456');

        $this->assertNotNull($data);
        $this->assertEquals('Test Game', $data['name']);
        $this->assertEquals('1,000,000 .. 2,000,000', $data['owners']);
        $this->assertEquals(120, $data['average_forever']);
    }

    public function test_fetch_game_details_returns_null_on_api_failure(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([], 500),
        ]);

        $data = $this->service->fetchGameDetails('123456');

        $this->assertNull($data);
    }

    public function test_fetch_game_details_returns_null_on_error_response(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([
                'error' => 'Invalid appid',
            ], 200),
        ]);

        $data = $this->service->fetchGameDetails('invalid');

        $this->assertNull($data);
    }

    public function test_fetch_top100_in_two_weeks_returns_array(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([
                '123456' => [
                    'appid' => 123456,
                    'name' => 'Popular Game',
                    'owners' => '10,000,000 .. 20,000,000',
                ],
            ], 200),
        ]);

        $data = $this->service->fetchTop100InTwoWeeks();

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function test_fetch_top100_in_two_weeks_returns_empty_on_failure(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([], 500),
        ]);

        $data = $this->service->fetchTop100InTwoWeeks();

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function test_is_stale_returns_true_when_never_synced(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 0,
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => null,
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertTrue($isStale);
    }

    public function test_is_stale_returns_true_for_high_priority_game_after_7_days(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 60, // High priority
            'first_release_date' => now()->subMonths(3), // Released 3 months ago (not recently released)
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => now()->subDays(8),
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertTrue($isStale);
    }

    public function test_is_stale_returns_false_for_high_priority_game_within_7_days(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 60, // High priority
            'first_release_date' => now()->subMonths(3), // Released 3 months ago (not recently released)
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => now()->subDays(5),
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertFalse($isStale);
    }

    public function test_is_stale_returns_true_for_low_priority_game_after_30_days(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 20, // Low priority
            'first_release_date' => now()->subMonths(3), // Released 3 months ago (not recently released)
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => now()->subDays(31),
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertTrue($isStale);
    }

    public function test_is_stale_returns_false_for_low_priority_game_within_30_days(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 20, // Low priority
            'first_release_date' => now()->subMonths(3), // Released 3 months ago (not recently released)
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => now()->subDays(15),
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertFalse($isStale);
    }

    public function test_is_stale_returns_true_when_synced_before_release(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 60,
            'first_release_date' => now()->subDays(2), // Released 2 days ago
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => now()->subDays(5), // Synced 5 days ago (before release)
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertTrue($isStale);
    }

    public function test_is_stale_returns_true_for_recently_released_game_after_3_days(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 20,
            'first_release_date' => now()->subDays(7), // Released 7 days ago (within 14 day window)
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => now()->subDays(4), // Synced 4 days ago (after 3 day threshold)
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertTrue($isStale);
    }

    public function test_is_stale_returns_false_for_recently_released_game_within_3_days(): void
    {
        $game = Game::factory()->create([
            'update_priority' => 20,
            'first_release_date' => now()->subDays(7), // Released 7 days ago (within 14 day window)
        ]);

        $sourceLink = new GameExternalSource([
            'game_id' => $game->id,
            'external_game_source_id' => 1,
            'external_uid' => '123456',
            'last_synced_at' => now()->subDays(2), // Synced 2 days ago (within 3 day threshold)
        ]);
        $sourceLink->setRelation('game', $game);

        $isStale = $this->service->isStale($game, $sourceLink);

        $this->assertFalse($isStale);
    }

    public function test_sync_game_data_creates_steam_game_data_record(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([
                'appid' => 123456,
                'name' => 'Test Game',
                'owners' => '1,000,000 .. 2,000,000',
                'players_forever' => '500,000',
                'average_forever' => 120,
                'ccu' => 5000,
                'price' => 1999,
                'tags' => ['Action' => 1000, 'Adventure' => 500],
            ], 200),
        ]);

        $steamSource = ExternalGameSource::create([
            'igdb_id' => 1,
            'name' => 'Steam',
            'slug' => 'steam',
        ]);

        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        $sourceLink = GameExternalSource::create([
            'game_id' => $game->id,
            'external_game_source_id' => $steamSource->id,
            'external_uid' => '123456',
            'sync_status' => 'pending',
        ]);

        $result = $this->service->syncGameData($sourceLink);

        $this->assertTrue($result);
        $this->assertDatabaseHas('steam_game_data', [
            'game_id' => $game->id,
            'steam_app_id' => '123456',
            'owners' => '1,000,000 .. 2,000,000',
            'average_forever' => 120,
            'ccu' => 5000,
            'price' => 1999,
        ]);

        $sourceLink->refresh();
        $this->assertEquals('synced', $sourceLink->sync_status);
        $this->assertNotNull($sourceLink->last_synced_at);
    }

    public function test_sync_game_data_marks_as_failed_on_api_error(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([], 500),
        ]);

        $steamSource = ExternalGameSource::create([
            'igdb_id' => 1,
            'name' => 'Steam',
            'slug' => 'steam',
        ]);

        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        $sourceLink = GameExternalSource::create([
            'game_id' => $game->id,
            'external_game_source_id' => $steamSource->id,
            'external_uid' => '123456',
            'sync_status' => 'pending',
            'retry_count' => 0,
        ]);

        $result = $this->service->syncGameData($sourceLink);

        $this->assertFalse($result);

        $sourceLink->refresh();
        $this->assertEquals('failed', $sourceLink->sync_status);
        $this->assertEquals(1, $sourceLink->retry_count);
        $this->assertNotNull($sourceLink->next_retry_at);
    }

    public function test_sync_game_data_updates_existing_record(): void
    {
        Http::fake([
            'steamspy.com/api.php*' => Http::response([
                'appid' => 123456,
                'name' => 'Test Game',
                'owners' => '2,000,000 .. 5,000,000', // Updated owners
                'average_forever' => 180, // Updated playtime
                'ccu' => 8000, // Updated CCU
            ], 200),
        ]);

        $steamSource = ExternalGameSource::create([
            'igdb_id' => 1,
            'name' => 'Steam',
            'slug' => 'steam',
        ]);

        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        // Create existing SteamGameData
        SteamGameData::create([
            'game_id' => $game->id,
            'steam_app_id' => '123456',
            'owners' => '1,000,000 .. 2,000,000',
            'average_forever' => 120,
            'ccu' => 5000,
        ]);

        $sourceLink = GameExternalSource::create([
            'game_id' => $game->id,
            'external_game_source_id' => $steamSource->id,
            'external_uid' => '123456',
            'sync_status' => 'pending',
        ]);

        $result = $this->service->syncGameData($sourceLink);

        $this->assertTrue($result);

        // Verify it was updated, not duplicated
        $this->assertEquals(1, SteamGameData::where('game_id', $game->id)->count());

        $this->assertDatabaseHas('steam_game_data', [
            'game_id' => $game->id,
            'owners' => '2,000,000 .. 5,000,000',
            'average_forever' => 180,
            'ccu' => 8000,
        ]);
    }
}
