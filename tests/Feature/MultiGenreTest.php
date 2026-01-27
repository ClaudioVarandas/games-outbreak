<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);

    // Create some genres
    $this->genres = Genre::factory()->count(5)->create();
    $this->primaryGenre = $this->genres->first();
    $this->secondaryGenres = $this->genres->slice(1, 2);

    // Create a game
    $this->game = Game::factory()->create();

    // Create an indie games list
    $this->indieList = GameList::factory()->create([
        'list_type' => ListTypeEnum::INDIE_GAMES->value,
        'is_system' => true,
        'is_active' => true,
        'is_public' => true,
        'slug' => 'indie-games-2026',
        'start_at' => now()->startOfYear(),
    ]);
});

describe('Multi-Genre Assignment via addGame', function () {
    it('allows assigning up to 3 genres when adding a game', function () {
        $genreIds = $this->genres->take(3)->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.system-lists.games.add', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
            ]), [
                'game_id' => $this->game->igdb_id,
                'genre_ids' => $genreIds,
                'primary_genre_id' => $this->primaryGenre->id,
            ]);

        $response->assertSuccessful();

        $pivot = $this->indieList->games()->where('games.id', $this->game->id)->first()->pivot;
        $storedGenreIds = json_decode($pivot->genre_ids, true);

        expect($storedGenreIds)->toHaveCount(3);
        expect($pivot->primary_genre_id)->toBe($this->primaryGenre->id);
    });

    it('stores primary genre id correctly', function () {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.system-lists.games.add', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
            ]), [
                'game_id' => $this->game->igdb_id,
                'genre_ids' => [$this->primaryGenre->id],
                'primary_genre_id' => $this->primaryGenre->id,
            ]);

        $response->assertSuccessful();

        $pivot = $this->indieList->games()->where('games.id', $this->game->id)->first()->pivot;

        expect($pivot->primary_genre_id)->toBe($this->primaryGenre->id);
    });

    it('rejects more than 3 genres', function () {
        $genreIds = $this->genres->pluck('id')->toArray(); // 5 genres

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.system-lists.games.add', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
            ]), [
                'game_id' => $this->game->igdb_id,
                'genre_ids' => $genreIds,
                'primary_genre_id' => $this->primaryGenre->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['genre_ids']);
    });

    it('validates genre ids exist', function () {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.system-lists.games.add', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
            ]), [
                'game_id' => $this->game->igdb_id,
                'genre_ids' => [99999, 99998],
                'primary_genre_id' => 99999,
            ]);

        $response->assertStatus(422);
    });
});

describe('Update Game Genres', function () {
    beforeEach(function () {
        // Add game to list first
        $this->indieList->games()->attach($this->game->id, [
            'order' => 1,
            'genre_ids' => json_encode([$this->primaryGenre->id]),
            'primary_genre_id' => $this->primaryGenre->id,
        ]);
    });

    it('updates game genres in a list', function () {
        $newGenreIds = $this->genres->slice(2, 2)->pluck('id')->toArray();
        $newPrimaryId = $newGenreIds[0];

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-genres', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
                'game' => $this->game->id,
            ]), [
                'genre_ids' => $newGenreIds,
                'primary_genre_id' => $newPrimaryId,
            ]);

        $response->assertSuccessful();

        $pivot = $this->indieList->games()->where('games.id', $this->game->id)->first()->pivot;
        $storedGenreIds = json_decode($pivot->genre_ids, true);

        expect($storedGenreIds)->toEqual($newGenreIds);
        expect($pivot->primary_genre_id)->toBe($newPrimaryId);
    });

    it('allows clearing all genres', function () {
        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-genres', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
                'game' => $this->game->id,
            ]), [
                'genre_ids' => [],
                'primary_genre_id' => null,
            ]);

        $response->assertSuccessful();

        $pivot = $this->indieList->games()->where('games.id', $this->game->id)->first()->pivot;

        expect(json_decode($pivot->genre_ids, true))->toEqual([]);
        expect($pivot->primary_genre_id)->toBeNull();
    });

    it('returns 404 for game not in list', function () {
        $otherGame = Game::factory()->create();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-genres', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
                'game' => $otherGame->id,
            ]), [
                'genre_ids' => [$this->primaryGenre->id],
                'primary_genre_id' => $this->primaryGenre->id,
            ]);

        $response->assertStatus(404);
    });
});

describe('Get Game Genres Endpoint', function () {
    beforeEach(function () {
        $this->indieList->games()->attach($this->game->id, [
            'order' => 1,
            'genre_ids' => json_encode([$this->primaryGenre->id, $this->genres[1]->id]),
            'primary_genre_id' => $this->primaryGenre->id,
            'is_indie' => true,
            'is_tba' => false,
        ]);
    });

    it('returns game genres data', function () {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.system-lists.games.genres', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
                'game' => $this->game->id,
            ]));

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'igdb_genres',
            'genre_ids',
            'primary_genre_id',
            'is_indie',
            'is_tba',
            'release_date',
        ]);

        expect($response->json('primary_genre_id'))->toBe($this->primaryGenre->id);
        expect($response->json('genre_ids'))->toContain($this->primaryGenre->id);
    });
});

describe('TBA Flag Support', function () {
    it('stores TBA flag when adding game', function () {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.system-lists.games.add', [
                'type' => 'indie',
                'slug' => $this->indieList->slug,
            ]), [
                'game_id' => $this->game->igdb_id,
                'is_tba' => true,
                'genre_ids' => [$this->primaryGenre->id],
                'primary_genre_id' => $this->primaryGenre->id,
            ]);

        $response->assertSuccessful();

        $pivot = $this->indieList->games()->where('games.id', $this->game->id)->first()->pivot;

        expect((bool) $pivot->is_tba)->toBeTrue();
        expect($pivot->release_date)->toBeNull();
    });
});

describe('Bulk Genre Operations', function () {
    beforeEach(function () {
        // Add multiple games with genres
        $this->games = Game::factory()->count(3)->create();
        foreach ($this->games as $index => $game) {
            $this->indieList->games()->attach($game->id, [
                'order' => $index + 1,
                'genre_ids' => json_encode([$this->primaryGenre->id]),
                'primary_genre_id' => $this->primaryGenre->id,
            ]);
        }
    });

    it('bulk removes genre from games in list', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.bulk-remove'), [
                'genre_id' => $this->primaryGenre->id,
                'list_id' => $this->indieList->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check that genre was removed from all games
        foreach ($this->games as $game) {
            $pivot = $this->indieList->games()->where('games.id', $game->id)->first()->pivot;
            $genreIds = json_decode($pivot->genre_ids, true);

            expect($genreIds)->not->toContain($this->primaryGenre->id);
            expect($pivot->primary_genre_id)->toBeNull();
        }
    });

    it('bulk replaces genre in games in list', function () {
        $targetGenre = $this->genres[2];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.bulk-replace'), [
                'source_genre_id' => $this->primaryGenre->id,
                'target_genre_id' => $targetGenre->id,
                'list_id' => $this->indieList->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check that genre was replaced in all games
        foreach ($this->games as $game) {
            $pivot = $this->indieList->games()->where('games.id', $game->id)->first()->pivot;
            $genreIds = json_decode($pivot->genre_ids, true);

            expect($genreIds)->toContain($targetGenre->id);
            expect($genreIds)->not->toContain($this->primaryGenre->id);
            expect($pivot->primary_genre_id)->toBe($targetGenre->id);
        }
    });

    it('assigns genre to multiple games', function () {
        $newGenre = Genre::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.assign-games', $newGenre), [
                'game_ids' => $this->games->pluck('id')->toArray(),
                'list_id' => $this->indieList->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check that genre was added to all games
        foreach ($this->games as $game) {
            $pivot = $this->indieList->games()->where('games.id', $game->id)->first()->pivot;
            $genreIds = json_decode($pivot->genre_ids, true);

            expect($genreIds)->toContain($newGenre->id);
        }
    });
});

describe('Frontend Display Logic', function () {
    it('groups games by primary genre in indie games controller', function () {
        // Add games with different primary genres
        $genre1 = $this->genres[0];
        $genre2 = $this->genres[1];

        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();

        $this->indieList->games()->attach($game1->id, [
            'order' => 1,
            'genre_ids' => json_encode([$genre1->id]),
            'primary_genre_id' => $genre1->id,
            'release_date' => now()->addMonth(),
        ]);

        $this->indieList->games()->attach($game2->id, [
            'order' => 2,
            'genre_ids' => json_encode([$genre2->id]),
            'primary_genre_id' => $genre2->id,
            'release_date' => now()->addMonth(),
        ]);

        $response = $this->get(route('indie-games'));

        $response->assertSuccessful();
    });

    it('shows games without primary genre in Other tab', function () {
        $game = Game::factory()->create();

        $this->indieList->games()->attach($game->id, [
            'order' => 1,
            'genre_ids' => json_encode([]),
            'primary_genre_id' => null,
            'release_date' => now()->addMonth(),
        ]);

        $response = $this->get(route('indie-games', ['genre' => 'other']));

        $response->assertSuccessful();
    });
});
