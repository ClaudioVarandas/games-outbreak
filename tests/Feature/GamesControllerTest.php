<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Genre;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GamesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
            'store.steampowered.com/api/appdetails*' => Http::response([], 200),
        ]);
    }

    public function test_upcoming_page_loads_successfully(): void
    {
        Game::factory()->upcoming()->count(5)->create();

        $response = $this->get('/upcoming');

        $response->assertStatus(200);
        $response->assertViewIs('upcoming.index');
    }

    public function test_upcoming_page_filters_by_date_range(): void
    {
        $today = now();
        $upcomingGame = Game::factory()->create([
            'first_release_date' => $today->copy()->addDays(10),
        ]);
        $pastGame = Game::factory()->create([
            'first_release_date' => $today->copy()->subDays(10),
        ]);

        $response = $this->get('/upcoming?start_date='.$today->format('Y-m-d').'&end_date='.$today->copy()->addDays(30)->format('Y-m-d'));

        $response->assertStatus(200);
        $response->assertViewHas('games', function ($games) use ($upcomingGame, $pastGame) {
            return $games->contains('id', $upcomingGame->id) &&
                   ! $games->contains('id', $pastGame->id);
        });
    }

    public function test_upcoming_page_filters_by_platform(): void
    {
        $platform = Platform::factory()->create(['igdb_id' => 6]);

        // Ensure games are within the 90-day date range
        $today = \Carbon\Carbon::today();
        $maxDate = $today->copy()->addDays(90);

        $gameWithPlatform = Game::factory()->create([
            'first_release_date' => $today->copy()->addDays(30), // Within range
        ]);
        $gameWithPlatform->platforms()->attach($platform->id);

        $gameWithoutPlatform = Game::factory()->create([
            'first_release_date' => $today->copy()->addDays(45), // Within range but no platform
        ]);

        $response = $this->get('/upcoming?platforms[]='.$platform->igdb_id);

        $response->assertStatus(200);
        $response->assertViewHas('games', function ($games) use ($gameWithPlatform, $gameWithoutPlatform) {
            return $games->contains('id', $gameWithPlatform->id) &&
                   ! $games->contains('id', $gameWithoutPlatform->id);
        });
    }

    public function test_upcoming_page_filters_by_genre(): void
    {
        $genre = Genre::factory()->create();

        // Ensure games are within the 90-day date range
        $today = \Carbon\Carbon::today();
        $maxDate = $today->copy()->addDays(90);

        $gameWithGenre = Game::factory()->create([
            'first_release_date' => $today->copy()->addDays(30), // Within range
        ]);
        $gameWithGenre->genres()->attach($genre->id);

        $gameWithoutGenre = Game::factory()->create([
            'first_release_date' => $today->copy()->addDays(45), // Within range but no genre
        ]);

        $response = $this->get('/upcoming?genres[]='.$genre->id);

        $response->assertStatus(200);
        $response->assertViewHas('games', function ($games) use ($gameWithGenre, $gameWithoutGenre) {
            return $games->contains('id', $gameWithGenre->id) &&
                   ! $games->contains('id', $gameWithoutGenre->id);
        });
    }

    public function test_most_wanted_page_loads_successfully(): void
    {
        Game::factory()->upcoming()->count(5)->create();

        $response = $this->get('/most-wanted');

        $response->assertStatus(200);
        $response->assertViewIs('most-wanted.index');
    }

    public function test_game_detail_page_loads_for_existing_game(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
        ]);

        $response = $this->get("/game/{$game->slug}");

        $response->assertStatus(200);
        $response->assertViewIs('games.show');
        $response->assertViewHas('game', $game);
    }

    public function test_game_detail_page_fetches_new_game_from_igdb(): void
    {
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([
                [
                    'id' => 99999,
                    'name' => 'New Game from IGDB',
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
        ]);

        $response = $this->get('/game/igdb/99999');

        $response->assertRedirect();
        $this->assertDatabaseHas('games', [
            'igdb_id' => 99999,
            'name' => 'New Game from IGDB',
        ]);

        // Follow the redirect and verify the game page loads
        $game = Game::where('igdb_id', 99999)->first();
        $followedResponse = $this->get("/game/{$game->slug}");
        $followedResponse->assertStatus(200);
        $followedResponse->assertViewIs('games.show');
    }

    public function test_game_detail_page_returns_404_for_invalid_game(): void
    {
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([], 200),
        ]);

        $response = $this->get('/game/999999');

        $response->assertStatus(404);
    }

    public function test_search_api_returns_json_response(): void
    {
        $game = Game::factory()->create([
            'name' => 'Test Game',
        ]);

        $response = $this->get('/api/search?q=Test');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'igdb_id',
                'name',
                'release',
                'cover_url',
                'platforms',
            ],
        ]);
    }

    public function test_search_api_handles_igdb_id_query(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
        ]);

        $response = $this->get('/api/search?q=12345');

        $response->assertStatus(200);
        $response->assertJson([
            [
                'igdb_id' => 12345,
                'name' => $game->name,
            ],
        ]);
    }

    public function test_search_results_page_loads(): void
    {
        Game::factory()->count(3)->create();

        $response = $this->get('/search?q=test');

        $response->assertStatus(200);
        $response->assertViewIs('search.results');
    }

    public function test_similar_games_api_returns_json(): void
    {
        $game = Game::factory()->withSimilarGames([
            ['id' => 11111, 'name' => 'Similar Game 1'],
            ['id' => 22222, 'name' => 'Similar Game 2'],
        ])->create([
            'igdb_id' => 12345,
        ]);

        Http::fake([
            'api.igdb.com/v4/games' => Http::response([
                [
                    'id' => 11111,
                    'name' => 'Similar Game 1',
                    'cover' => ['image_id' => 'co111'],
                    'platforms' => [],
                    'genres' => [],
                    'game_modes' => [],
                    'external_games' => [],
                    'websites' => [],
                    'game_type' => 0,
                    'release_dates' => null,
                ],
            ], 200),
        ]);

        $response = $this->get("/api/game/{$game->slug}/similar");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'games' => [
                '*' => [
                    'igdb_id',
                    'name',
                    'cover_url',
                    'platforms',
                ],
            ],
        ]);
    }

    public function test_similar_games_api_returns_empty_for_no_similar_games(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'similar_games' => null,
        ]);

        $response = $this->get("/api/game/{$game->slug}/similar");

        $response->assertStatus(200);
        $response->assertJson(['games' => []]);
    }

    public function test_similar_games_html_endpoint_returns_view(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
        ]);

        $response = $this->get("/game/{$game->slug}/similar-games-html");

        $response->assertStatus(200);
        $response->assertViewIs('games.partials.similar-games');
    }
}
