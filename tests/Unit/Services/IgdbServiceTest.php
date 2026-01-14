<?php

namespace Tests\Unit\Services;

use App\DTOs\ExternalSourceData;
use App\Models\ExternalGameSource;
use App\Models\Game;
use App\Services\IgdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IgdbServiceTest extends TestCase
{
    use RefreshDatabase;

    private IgdbService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IgdbService;
        Cache::flush();
    }

    public function test_get_access_token_returns_token_from_api(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = $this->service->getAccessToken();

        $this->assertEquals('test-token-123', $token);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://id.twitch.tv/oauth2/token' &&
                   $request->data()['grant_type'] === 'client_credentials';
        });
    }

    public function test_get_access_token_caches_token(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'cached-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token1 = $this->service->getAccessToken();
        $token2 = $this->service->getAccessToken();

        $this->assertEquals('cached-token', $token1);
        $this->assertEquals('cached-token', $token2);
        // Should only make one HTTP request due to caching
        Http::assertSentCount(1);
    }

    public function test_get_access_token_throws_exception_on_failure(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['error' => 'Invalid credentials'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to obtain IGDB access token');

        $this->service->getAccessToken();
    }

    public function test_fetch_upcoming_games_returns_games_array(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
            'api.igdb.com/v4/games' => Http::response([
                [
                    'id' => 12345,
                    'name' => 'Test Game',
                    'first_release_date' => time() + 86400,
                    'cover' => ['image_id' => 'co123'],
                ],
            ], 200),
        ]);

        $games = $this->service->fetchUpcomingGames();

        $this->assertIsArray($games);
        $this->assertCount(1, $games);
        $this->assertEquals('Test Game', $games[0]['name']);
    }

    public function test_fetch_upcoming_games_handles_empty_response(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
            'api.igdb.com/v4/games' => Http::response([], 200),
        ]);

        $games = $this->service->fetchUpcomingGames();

        $this->assertIsArray($games);
        $this->assertEmpty($games);
    }

    public function test_get_cover_url_generates_correct_url(): void
    {
        $url = $this->service->getCoverUrl('co123', 'cover_big');

        $this->assertEquals('https://images.igdb.com/igdb/image/upload/t_cover_big/co123.jpg', $url);
    }

    public function test_get_cover_url_returns_placeholder_for_null(): void
    {
        $url = $this->service->getCoverUrl(null);

        $this->assertStringContainsString('placeholder', $url);
    }

    public function test_get_screenshot_url_generates_correct_url(): void
    {
        $url = $this->service->getScreenshotUrl('sc123', 'screenshot_big');

        $this->assertEquals('https://images.igdb.com/igdb/image/upload/t_screenshot_big/sc123.jpg', $url);
    }

    public function test_get_screenshot_url_returns_placeholder_for_null(): void
    {
        $url = $this->service->getScreenshotUrl(null);

        $this->assertStringContainsString('placeholder', $url);
    }

    public function test_get_youtube_embed_url_generates_correct_url(): void
    {
        $url = $this->service->getYouTubeEmbedUrl('dQw4w9WgXcQ');

        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0', $url);
    }

    public function test_get_youtube_embed_url_returns_empty_for_null(): void
    {
        $url = $this->service->getYouTubeEmbedUrl(null);

        $this->assertEquals('', $url);
    }

    public function test_get_youtube_thumbnail_url_generates_correct_url(): void
    {
        $url = $this->service->getYouTubeThumbnailUrl('dQw4w9WgXcQ');

        $this->assertEquals('https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', $url);
    }

    public function test_get_youtube_thumbnail_url_returns_placeholder_for_null(): void
    {
        $url = $this->service->getYouTubeThumbnailUrl(null);

        $this->assertStringContainsString('placeholder', $url);
    }

    public function test_enrich_with_steam_data_returns_games_with_steam_data(): void
    {
        $this->markTestSkipped('Deprecated: enrichWithSteamData() - See igdb_game_rework_13012026_spec.md');

        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '123456' => [
                    'success' => true,
                    'data' => [
                        'header_image' => 'https://cdn.akamai.steamstatic.com/steam/apps/123456/header.jpg',
                        'release_date' => ['date' => '1 Jan, 2024'],
                        'recommendations' => ['total' => 5000],
                    ],
                ],
            ], 200),
        ]);

        $igdbGames = [
            [
                'id' => 12345,
                'name' => 'Test Game',
                'external_games' => [
                    ['category' => 1, 'uid' => '123456'], // Steam
                ],
            ],
        ];

        $enriched = $this->service->enrichWithSteamData($igdbGames);

        $this->assertArrayHasKey('steam', $enriched[0]);
        $this->assertEquals(123456, $enriched[0]['steam']['appid']);
        $this->assertNotNull($enriched[0]['steam']['header_image']);
    }

    public function test_enrich_with_steam_data_caches_steam_data(): void
    {
        $this->markTestSkipped('Deprecated: enrichWithSteamData() - See igdb_game_rework_13012026_spec.md');

        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '123456' => [
                    'success' => true,
                    'data' => [
                        'header_image' => 'https://cdn.akamai.steamstatic.com/steam/apps/123456/header.jpg',
                        'release_date' => ['date' => '1 Jan, 2024'],
                    ],
                ],
            ], 200),
            'steamdb.info/*' => Http::response('', 200), // Mock SteamDB requests
        ]);

        $igdbGames = [
            [
                'id' => 12345,
                'name' => 'Test Game',
                'external_games' => [
                    ['category' => 1, 'uid' => '123456'],
                ],
            ],
        ];

        // First call
        $this->service->enrichWithSteamData($igdbGames);
        // Second call should use cache
        $this->service->enrichWithSteamData($igdbGames);

        // Should only make one Steam API request due to caching (SteamDB requests may be made but Steam API should be cached)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'store.steampowered.com/api/appdetails');
        }, 1);
    }

    public function test_enrich_with_steam_data_handles_missing_steam_appid(): void
    {
        $this->markTestSkipped('Deprecated: enrichWithSteamData() - See igdb_game_rework_13012026_spec.md');

        $igdbGames = [
            [
                'id' => 12345,
                'name' => 'Test Game',
                'external_games' => [],
            ],
        ];

        $enriched = $this->service->enrichWithSteamData($igdbGames);

        $this->assertArrayNotHasKey('steam', $enriched[0]);
    }

    public function test_enrich_with_steam_data_handles_steam_api_failure(): void
    {
        $this->markTestSkipped('Deprecated: enrichWithSteamData() - See igdb_game_rework_13012026_spec.md');

        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([], 500),
        ]);

        $igdbGames = [
            [
                'id' => 12345,
                'name' => 'Test Game',
                'external_games' => [
                    ['category' => 1, 'uid' => '123456'],
                ],
            ],
        ];

        $enriched = $this->service->enrichWithSteamData($igdbGames);

        // Should not crash, just return games without steam data
        $this->assertIsArray($enriched);
    }

    public function test_fetch_image_from_steamgriddb_returns_filename_on_success(): void
    {
        Http::fake([
            'www.steamgriddb.com/api/v2/search/autocomplete/*' => Http::response([
                'data' => [
                    ['id' => 100, 'types' => ['steam']],
                ],
            ], 200),
            'www.steamgriddb.com/api/v2/grids/game/*' => Http::response([
                'data' => [
                    [
                        'url' => 'https://example.com/image.jpg',
                        'style' => 'alternate',
                    ],
                ],
            ], 200),
            'example.com/image.jpg' => Http::response('image content', 200),
        ]);

        Storage::fake('public');

        $filename = $this->service->fetchImageFromSteamGridDb('Test Game', 'cover', 123456, 12345);

        $this->assertNotNull($filename);
        $this->assertStringContainsString('.jpg', $filename);
    }

    public function test_fetch_image_from_steamgriddb_returns_null_on_search_failure(): void
    {
        Http::fake([
            'www.steamgriddb.com/api/v2/search/autocomplete/*' => Http::response([], 404),
        ]);

        $filename = $this->service->fetchImageFromSteamGridDb('Test Game', 'cover');

        $this->assertNull($filename);
    }

    public function test_fetch_image_from_steamgriddb_returns_null_on_empty_search_results(): void
    {
        Http::fake([
            'www.steamgriddb.com/api/v2/search/autocomplete/*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $filename = $this->service->fetchImageFromSteamGridDb('Test Game', 'cover');

        $this->assertNull($filename);
    }

    public function test_fetch_steam_popular_upcoming_returns_collection(): void
    {
        Http::fake([
            'store.steampowered.com/search/results/*' => Http::response([
                'items' => [
                    [
                        'id' => 123456,
                        'name' => 'Popular Game',
                        'release_string' => 'Coming Soon',
                        'wishlist_count' => 50000,
                    ],
                ],
            ], 200),
        ]);

        $games = $this->service->fetchSteamPopularUpcoming(10);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $games);
        $this->assertGreaterThan(0, $games->count());
    }

    public function test_fetch_steam_popular_upcoming_handles_empty_response(): void
    {
        Http::fake([
            'store.steampowered.com/search/results/*' => Http::response([
                'items' => [],
            ], 200),
        ]);

        $games = $this->service->fetchSteamPopularUpcoming(10);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $games);
        $this->assertEmpty($games);
    }

    public function test_fetch_steam_popular_upcoming_handles_api_failure(): void
    {
        Http::fake([
            'store.steampowered.com/search/results/*' => Http::response([], 500),
        ]);

        $games = $this->service->fetchSteamPopularUpcoming(10);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $games);
        $this->assertEmpty($games);
    }

    // === External Sources Tests ===

    public function test_extract_external_sources_returns_collection_of_dtos(): void
    {
        $igdbGame = [
            'id' => 12345,
            'name' => 'Test Game',
            'external_games' => [
                [
                    'category' => 1,
                    'uid' => '123456',
                    'url' => 'https://store.steampowered.com/app/123456',
                ],
                [
                    'category' => 5,
                    'uid' => 'test-game-gog',
                    'url' => 'https://www.gog.com/game/test-game-gog',
                ],
            ],
        ];

        $sources = $this->service->extractExternalSources($igdbGame);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $sources);
        $this->assertCount(2, $sources);
        $this->assertInstanceOf(ExternalSourceData::class, $sources->first());
    }

    public function test_extract_external_sources_extracts_steam_source_correctly(): void
    {
        $igdbGame = [
            'id' => 12345,
            'name' => 'Test Game',
            'external_games' => [
                [
                    'category' => 1,
                    'uid' => '123456',
                    'url' => 'https://store.steampowered.com/app/123456',
                ],
            ],
        ];

        $sources = $this->service->extractExternalSources($igdbGame);

        $this->assertCount(1, $sources);
        $steamSource = $sources->first();
        $this->assertEquals(1, $steamSource->sourceId);
        $this->assertEquals('Steam', $steamSource->sourceName);
        $this->assertEquals('123456', $steamSource->externalUid);
        $this->assertEquals('https://store.steampowered.com/app/123456', $steamSource->externalUrl);
    }

    public function test_extract_external_sources_handles_empty_external_games(): void
    {
        $igdbGame = [
            'id' => 12345,
            'name' => 'Test Game',
            'external_games' => [],
        ];

        $sources = $this->service->extractExternalSources($igdbGame);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $sources);
        $this->assertEmpty($sources);
    }

    public function test_extract_external_sources_handles_missing_external_games(): void
    {
        $igdbGame = [
            'id' => 12345,
            'name' => 'Test Game',
        ];

        $sources = $this->service->extractExternalSources($igdbGame);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $sources);
        $this->assertEmpty($sources);
    }

    public function test_extract_external_sources_skips_entries_without_uid(): void
    {
        $igdbGame = [
            'id' => 12345,
            'name' => 'Test Game',
            'external_games' => [
                [
                    'category' => 1,
                    // Missing uid
                ],
                [
                    'category' => 5,
                    'uid' => 'valid-uid',
                ],
            ],
        ];

        $sources = $this->service->extractExternalSources($igdbGame);

        $this->assertCount(1, $sources);
        $this->assertEquals('valid-uid', $sources->first()->externalUid);
    }

    public function test_sync_external_sources_creates_pivot_records(): void
    {
        // Create external game source in DB
        $steamSource = ExternalGameSource::create([
            'igdb_id' => 1,
            'name' => 'Steam',
            'slug' => 'steam',
        ]);

        // Create game
        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        $igdbGame = [
            'id' => 12345,
            'name' => 'Test Game',
            'external_games' => [
                [
                    'category' => 1,
                    'uid' => '123456',
                    'url' => 'https://store.steampowered.com/app/123456',
                ],
            ],
        ];

        $this->service->syncExternalSources($game, $igdbGame);

        $this->assertDatabaseHas('game_external_sources', [
            'game_id' => $game->id,
            'external_game_source_id' => $steamSource->id,
            'external_uid' => '123456',
            'external_url' => 'https://store.steampowered.com/app/123456',
        ]);
    }

    public function test_sync_external_sources_updates_existing_records(): void
    {
        // Create external game source in DB
        $steamSource = ExternalGameSource::create([
            'igdb_id' => 1,
            'name' => 'Steam',
            'slug' => 'steam',
        ]);

        // Create game
        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        // First sync
        $igdbGame = [
            'id' => 12345,
            'external_games' => [
                [
                    'category' => 1,
                    'uid' => '123456',
                    'url' => null,
                ],
            ],
        ];

        $this->service->syncExternalSources($game, $igdbGame);

        // Second sync with updated URL
        $igdbGame['external_games'][0]['url'] = 'https://store.steampowered.com/app/123456';

        $this->service->syncExternalSources($game, $igdbGame);

        // Should have only one record (updated, not duplicated)
        $this->assertEquals(1, $game->gameExternalSources()->count());
        $this->assertDatabaseHas('game_external_sources', [
            'game_id' => $game->id,
            'external_uid' => '123456',
            'external_url' => 'https://store.steampowered.com/app/123456',
        ]);
    }

    public function test_sync_external_sources_ignores_unknown_sources(): void
    {
        // Create only Steam source in DB (not GOG)
        ExternalGameSource::create([
            'igdb_id' => 1,
            'name' => 'Steam',
            'slug' => 'steam',
        ]);

        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        $igdbGame = [
            'id' => 12345,
            'external_games' => [
                [
                    'category' => 1,
                    'uid' => '123456',
                ],
                [
                    'category' => 5, // GOG - not in our DB
                    'uid' => 'test-game-gog',
                ],
            ],
        ];

        $this->service->syncExternalSources($game, $igdbGame);

        // Should only have Steam record
        $this->assertEquals(1, $game->gameExternalSources()->count());
    }

    public function test_get_steam_app_id_from_sources_returns_steam_uid(): void
    {
        // Create external game source in DB
        $steamSource = ExternalGameSource::create([
            'igdb_id' => 1,
            'name' => 'Steam',
            'slug' => 'steam',
        ]);

        // Create game with external source
        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        $game->gameExternalSources()->create([
            'external_game_source_id' => $steamSource->id,
            'external_uid' => '123456',
        ]);

        $steamAppId = $this->service->getSteamAppIdFromSources($game);

        $this->assertEquals('123456', $steamAppId);
    }

    public function test_get_steam_app_id_from_sources_returns_null_when_no_steam_source(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Test Game',
        ]);

        $steamAppId = $this->service->getSteamAppIdFromSources($game);

        $this->assertNull($steamAppId);
    }
}
