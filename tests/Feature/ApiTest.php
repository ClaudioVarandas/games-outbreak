<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_api_returns_json(): void
    {
        $game = Game::factory()->create([
            'name' => 'Test Game',
        ]);

        $response = $this->getJson('/api/search?q=Test');

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

    public function test_search_api_handles_empty_query(): void
    {
        $response = $this->getJson('/api/search?q=');

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    public function test_search_api_handles_igdb_id_query(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
        ]);

        $response = $this->getJson('/api/search?q=12345');

        $response->assertStatus(200);
        $response->assertJson([
            [
                'igdb_id' => 12345,
                'name' => $game->name,
            ],
        ]);
    }

    public function test_similar_games_api_returns_json(): void
    {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'similar_games' => [
                ['id' => 11111, 'name' => 'Similar Game'],
            ],
        ]);

        $response = $this->getJson('/api/game/12345/similar');

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

        $response = $this->getJson('/api/game/12345/similar');

        $response->assertStatus(200);
        $response->assertJson(['games' => []]);
    }

    public function test_similar_games_api_handles_nonexistent_game(): void
    {
        $response = $this->getJson('/api/game/99999/similar');

        $response->assertStatus(404);
    }
}
