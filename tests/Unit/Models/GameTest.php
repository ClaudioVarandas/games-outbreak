<?php

namespace Tests\Unit\Models;

use App\Enums\GameTypeEnum;
use App\Models\Game;
use App\Models\Genre;
use App\Models\GameMode;
use App\Models\Platform;
use App\Services\IgdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_cover_url_returns_igdb_url_when_cover_exists(): void
    {
        $game = Game::factory()->create([
            'cover_image_id' => 'co123',
        ]);

        $url = $game->getCoverUrl();

        $this->assertStringContainsString('images.igdb.com', $url);
        $this->assertStringContainsString('co123', $url);
    }

    public function test_get_cover_url_returns_placeholder_when_no_cover(): void
    {
        $game = Game::factory()->create([
            'cover_image_id' => null,
            'hero_image_id' => null,
        ]);

        $url = $game->getCoverUrl();

        $this->assertStringContainsString('placeholder', $url);
    }

    public function test_get_cover_url_falls_back_to_hero_image(): void
    {
        $game = Game::factory()->create([
            'cover_image_id' => null,
            'hero_image_id' => 'hero123',
        ]);

        $url = $game->getCoverUrl();

        $this->assertStringContainsString('hero123', $url);
    }

    public function test_get_hero_image_url_returns_hero_when_exists(): void
    {
        $game = Game::factory()->create([
            'hero_image_id' => 'hero123',
        ]);

        $url = $game->getHeroImageUrl();

        $this->assertStringContainsString('hero123', $url);
    }

    public function test_get_hero_image_url_falls_back_to_cover(): void
    {
        $game = Game::factory()->create([
            'hero_image_id' => null,
            'cover_image_id' => 'co123',
        ]);

        $url = $game->getHeroImageUrl();

        $this->assertStringContainsString('co123', $url);
    }

    public function test_get_logo_image_url_returns_logo_when_exists(): void
    {
        $game = Game::factory()->create([
            'logo_image_id' => 'logo123',
        ]);

        $url = $game->getLogoImageUrl();

        $this->assertStringContainsString('logo123', $url);
    }

    public function test_get_logo_image_url_returns_placeholder_when_no_logo(): void
    {
        $game = Game::factory()->create([
            'logo_image_id' => null,
        ]);

        $url = $game->getLogoImageUrl();

        $this->assertStringContainsString('placeholder', $url);
    }

    public function test_get_game_type_enum_returns_correct_enum(): void
    {
        $game = Game::factory()->create([
            'game_type' => 0,
        ]);

        $enum = $game->getGameTypeEnum();

        $this->assertInstanceOf(GameTypeEnum::class, $enum);
        $this->assertEquals(GameTypeEnum::MAIN, $enum);
    }

    public function test_get_game_type_enum_detects_bundle_by_name(): void
    {
        $game = Game::factory()->create([
            'name' => 'Game Bundle Collection',
            'game_type' => 0, // Not marked as bundle in IGDB
        ]);

        $enum = $game->getGameTypeEnum();

        $this->assertEquals(GameTypeEnum::BUNDLE, $enum);
    }

    public function test_fetch_from_igdb_if_missing_returns_existing_game(): void
    {
        $existingGame = Game::factory()->create([
            'igdb_id' => 12345,
        ]);

        $igdbService = app(IgdbService::class);
        $result = Game::fetchFromIgdbIfMissing(12345, $igdbService);

        $this->assertNotNull($result);
        $this->assertEquals($existingGame->id, $result->id);
    }

    public function test_fetch_from_igdb_if_missing_fetches_new_game(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
            'api.igdb.com/v4/games' => Http::response([
                [
                    'id' => 99999,
                    'name' => 'New Game',
                    'summary' => 'Test summary',
                    'first_release_date' => time() + 86400,
                    'cover' => ['image_id' => 'co999'],
                    'platforms' => [],
                    'genres' => [],
                    'game_modes' => [],
                    'external_games' => [],
                    'websites' => [],
                    'game_type' => 0,
                    'release_dates' => null,
                ],
            ], 200),
            'store.steampowered.com/api/appdetails*' => Http::response([], 200),
        ]);

        $igdbService = app(IgdbService::class);
        $result = Game::fetchFromIgdbIfMissing(99999, $igdbService);

        $this->assertNotNull($result);
        $this->assertEquals(99999, $result->igdb_id);
        $this->assertEquals('New Game', $result->name);
    }

    public function test_fetch_from_igdb_if_missing_returns_null_on_api_failure(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
            'api.igdb.com/v4/games' => Http::response([], 404),
        ]);

        $igdbService = app(IgdbService::class);
        $result = Game::fetchFromIgdbIfMissing(99999, $igdbService);

        $this->assertNull($result);
    }

    public function test_sync_release_dates_creates_release_dates(): void
    {
        $game = Game::factory()->create();
        $platform = Platform::factory()->create(['igdb_id' => 6]);

        $igdbReleaseDates = [
            [
                'id' => 12345,
                'platform' => 6,
                'date' => strtotime('2024-01-15'),
                'y' => 2024,
                'm' => 1,
                'd' => 15,
                'region' => 1,
                'human' => 'Jan 15, 2024',
            ],
        ];

        Game::syncReleaseDates($game, $igdbReleaseDates);

        $this->assertEquals(1, $game->releaseDates()->count());
        $releaseDate = $game->releaseDates()->first();
        $this->assertEquals($platform->id, $releaseDate->platform_id);
        $this->assertEquals('15/01/2024', $releaseDate->formatted_date);
    }

    public function test_sync_release_dates_removes_all_when_null(): void
    {
        $game = Game::factory()->create();
        $platform = Platform::factory()->create(['igdb_id' => 6]);

        // Create initial release date
        \App\Models\GameReleaseDate::create([
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'date' => now(),
            'is_manual' => false,
        ]);

        Game::syncReleaseDates($game, null);

        $this->assertEquals(0, $game->releaseDates()->count());
    }

    public function test_sync_release_dates_preserves_manual_dates(): void
    {
        $game = Game::factory()->create();
        $platform = Platform::factory()->create(['igdb_id' => 6]);

        // Create manual release date
        \App\Models\GameReleaseDate::create([
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'date' => now(),
            'is_manual' => true,
        ]);

        Game::syncReleaseDates($game, null);

        // Manual dates should be preserved
        $this->assertEquals(1, $game->releaseDates()->count());
    }

    public function test_sync_release_dates_handles_null_igdb_ids_without_duplicates(): void
    {
        $game = Game::factory()->create();
        $platform = Platform::factory()->create(['igdb_id' => 6]);

        // First sync with release date without IGDB ID
        $igdbReleaseDates = [
            [
                // No 'id' field - simulating IGDB data without ID
                'platform' => 6,
                'date' => strtotime('2024-03-27'),
                'y' => 2024,
                'm' => 3,
                'd' => 27,
                'region' => null,
                'human' => 'Mar 27, 2024',
            ],
        ];

        Game::syncReleaseDates($game, $igdbReleaseDates);
        $this->assertEquals(1, $game->releaseDates()->count());

        // Sync again with same data - should NOT create duplicate
        Game::syncReleaseDates($game, $igdbReleaseDates);
        $this->assertEquals(1, $game->releaseDates()->count(), 'Should not create duplicate when syncing null IGDB ID again');

        // Sync third time - still no duplicate
        Game::syncReleaseDates($game, $igdbReleaseDates);
        $this->assertEquals(1, $game->releaseDates()->count(), 'Should remain stable across multiple syncs');
    }

    public function test_sync_release_dates_updates_existing_when_status_changes(): void
    {
        $game = Game::factory()->create();
        $platform = Platform::factory()->create(['igdb_id' => 6]);
        $status1 = \App\Models\ReleaseDateStatus::factory()->create(['igdb_id' => 1, 'name' => 'TBA']);
        $status2 = \App\Models\ReleaseDateStatus::factory()->create(['igdb_id' => 2, 'name' => 'Released']);

        // Initial sync with status TBA
        $igdbReleaseDates = [
            [
                'id' => 12345,
                'platform' => 6,
                'date' => strtotime('2024-03-27'),
                'y' => 2024,
                'm' => 3,
                'd' => 27,
                'region' => null,
                'status' => 1,
                'human' => 'Mar 27, 2024',
            ],
        ];

        Game::syncReleaseDates($game, $igdbReleaseDates);
        $this->assertEquals(1, $game->releaseDates()->count());
        $releaseDate = $game->releaseDates()->first();
        $this->assertEquals('TBA', $releaseDate->status->name);

        // Update status to Released
        $igdbReleaseDates[0]['status'] = 2;
        Game::syncReleaseDates($game, $igdbReleaseDates);

        $game->refresh();
        $this->assertEquals(1, $game->releaseDates()->count(), 'Should not create duplicate on status change');
        $releaseDate = $game->releaseDates()->first();
        $this->assertEquals('Released', $releaseDate->status->name, 'Status should be updated');
    }

    public function test_game_has_platforms_relationship(): void
    {
        $game = Game::factory()->create();
        $platform = Platform::factory()->create();

        $game->platforms()->attach($platform->id);

        $this->assertTrue($game->platforms->contains($platform));
    }

    public function test_game_has_genres_relationship(): void
    {
        $game = Game::factory()->create();
        $genre = Genre::factory()->create();

        $game->genres()->attach($genre->id);

        $this->assertTrue($game->genres->contains($genre));
    }

    public function test_game_has_game_modes_relationship(): void
    {
        $game = Game::factory()->create();
        $gameMode = GameMode::factory()->create();

        $game->gameModes()->attach($gameMode->id);

        $this->assertTrue($game->gameModes->contains($gameMode));
    }

    public function test_get_steam_price_returns_formatted_price(): void
    {
        $game = Game::factory()->create([
            'steam_data' => [
                'price_overview' => [
                    'final_formatted' => '$29.99',
                ],
            ],
        ]);

        $price = $game->getSteamPrice();

        $this->assertEquals('$29.99', $price);
    }

    public function test_get_steam_price_returns_null_when_no_price(): void
    {
        $game = Game::factory()->create([
            'steam_data' => null,
        ]);

        $price = $game->getSteamPrice();

        $this->assertNull($price);
    }
}
