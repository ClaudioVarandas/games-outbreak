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

    public function test_transform_release_dates_formats_dates_correctly(): void
    {
        $releaseDates = [
            [
                'platform' => 6,
                'date' => strtotime('2024-01-15'),
                'region' => 1,
            ],
        ];

        $transformed = Game::transformReleaseDates($releaseDates);

        $this->assertNotNull($transformed);
        $this->assertEquals('15/01/2024', $transformed[0]['release_date']);
        $this->assertArrayHasKey('platform_name', $transformed[0]);
    }

    public function test_transform_release_dates_returns_null_for_empty_array(): void
    {
        $result = Game::transformReleaseDates([]);

        $this->assertNull($result);
    }

    public function test_transform_release_dates_returns_null_for_null(): void
    {
        $result = Game::transformReleaseDates(null);

        $this->assertNull($result);
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
