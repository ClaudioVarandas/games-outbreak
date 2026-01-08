<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Game Show Route with Slug', function () {
    it('can access game page via slug', function () {
        $game = Game::factory()->create([
            'name' => 'Test Game',
            'first_release_date' => new DateTime('2023-06-15'),
        ]);

        $response = $this->get("/game/{$game->slug}");

        $response->assertStatus(200);
        $response->assertSee($game->name);
    });

    it('returns 404 for non-existent slug', function () {
        $response = $this->get('/game/non-existent-slug-2023');

        $response->assertStatus(404);
    });

    it('generates correct slug format in URL', function () {
        $game = Game::factory()->create([
            'name' => 'The Legend of Zelda',
            'first_release_date' => new DateTime('2023-05-12'),
        ]);

        expect($game->slug)->toBe('the-legend-of-zelda-2023');

        $response = $this->get('/game/the-legend-of-zelda-2023');

        $response->assertStatus(200);
    });
});

describe('IGDB Fallback Route', function () {
    it('redirects to slug URL for existing game', function () {
        $game = Game::factory()->create([
            'igdb_id' => 12345,
            'name' => 'Existing Game',
            'first_release_date' => new DateTime('2022-01-01'),
        ]);

        $response = $this->get('/game/igdb/12345');

        $response->assertRedirect("/game/{$game->slug}");
    });

    it('only accepts numeric IGDB IDs', function () {
        $response = $this->get('/game/igdb/not-a-number');

        $response->assertStatus(404);
    });
});

describe('Search API Slug Response', function () {
    it('returns slug for games that exist in database', function () {
        $game = Game::factory()->create([
            'igdb_id' => 99999,
            'name' => 'Database Game',
            'first_release_date' => new DateTime('2023-01-01'),
        ]);

        // Mock the IGDB API response would be needed for full test
        // For now, test the searchByIgdbId endpoint which uses DB
        $response = $this->getJson('/api/search?q=99999');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'igdb_id' => 99999,
            'slug' => $game->slug,
        ]);
    });
});

describe('Route Helper with Slug', function () {
    it('generates correct URL using route helper', function () {
        $game = Game::factory()->create([
            'name' => 'Route Test Game',
            'first_release_date' => new DateTime('2024-03-15'),
        ]);

        $url = route('game.show', $game);

        expect($url)->toContain('/game/route-test-game-2024');
    });

    it('uses slug as route key name', function () {
        $game = Game::factory()->create([
            'name' => 'Key Name Test',
            'first_release_date' => new DateTime('2023-01-01'),
        ]);

        expect($game->getRouteKeyName())->toBe('slug');
        expect($game->getRouteKey())->toBe($game->slug);
    });
});

describe('Game Card URL Generation', function () {
    it('uses slug URL when game has slug', function () {
        $game = Game::factory()->create([
            'name' => 'Card Test Game',
            'first_release_date' => new DateTime('2023-07-20'),
        ]);

        expect($game->slug)->not->toBeNull();

        $expectedUrl = route('game.show', $game);
        expect($expectedUrl)->toContain('/game/card-test-game-2023');
    });

    it('falls back to IGDB route when slug is null', function () {
        // Create game without triggering model events (simulating pre-migration state)
        $game = new Game;
        $game->igdb_id = 55555;
        $game->name = 'No Slug Game';
        $game->slug = null;

        $fallbackUrl = route('game.show.igdb', $game->igdb_id);
        expect($fallbackUrl)->toContain('/game/igdb/55555');
    });
});

describe('Duplicate Slug Handling in Routes', function () {
    it('handles games with same name and year via counter suffix', function () {
        $game1 = Game::factory()->create([
            'name' => 'Duplicate Game',
            'first_release_date' => new DateTime('2023-01-01'),
        ]);

        $game2 = Game::factory()->create([
            'name' => 'Duplicate Game',
            'first_release_date' => new DateTime('2023-06-01'),
        ]);

        expect($game1->slug)->toBe('duplicate-game-2023');
        expect($game2->slug)->toBe('duplicate-game-2023-2');

        // Both should be accessible
        $this->get("/game/{$game1->slug}")->assertStatus(200);
        $this->get("/game/{$game2->slug}")->assertStatus(200);
    });
});

describe('Similar Games Route with Slug', function () {
    it('can access similar games HTML via slug', function () {
        $game = Game::factory()->create([
            'name' => 'Similar Test',
            'first_release_date' => new DateTime('2023-01-01'),
            'similar_games' => [],
        ]);

        $response = $this->get("/game/{$game->slug}/similar-games-html");

        $response->assertStatus(200);
    });

    it('can access similar games API via slug', function () {
        $game = Game::factory()->create([
            'name' => 'API Similar Test',
            'first_release_date' => new DateTime('2023-01-01'),
            'similar_games' => [],
        ]);

        $response = $this->getJson("/api/game/{$game->slug}/similar");

        $response->assertStatus(200);
        $response->assertJson(['games' => []]);
    });
});
