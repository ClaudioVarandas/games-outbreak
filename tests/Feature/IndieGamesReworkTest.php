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
});

describe('Indie Games Page', function () {
    it('displays the indie games page when no active list exists', function () {
        $response = $this->get(route('indie-games'));

        $response->assertStatus(200);
        $response->assertSee('Indie Games');
        $response->assertSee('No active indie games list');
    });

    it('displays the active indie list with games grouped by genre', function () {
        $list = GameList::factory()->create([
            'name' => 'Indies 2026',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $game1 = Game::factory()->create(['name' => 'Indie Game One']);
        $game2 = Game::factory()->create(['name' => 'Indie Game Two']);

        $list->games()->attach($game1->id, [
            'order' => 1,
            'indie_genre' => 'metroidvania',
        ]);
        $list->games()->attach($game2->id, [
            'order' => 2,
            'indie_genre' => 'roguelike',
        ]);

        $response = $this->get(route('indie-games'));

        $response->assertStatus(200);
        $response->assertSee('Indies 2026');
        $response->assertSee('Indie Game One');
        $response->assertSee('Indie Game Two');
    });

    it('does not show inactive indie lists', function () {
        $list = GameList::factory()->create([
            'name' => 'Inactive Indies',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_active' => false,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $response = $this->get(route('indie-games'));

        $response->assertStatus(200);
        $response->assertDontSee('Inactive Indies');
    });

    it('accepts year parameter to show specific year list', function () {
        $list2025 = GameList::factory()->create([
            'name' => 'Indies 2025',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2025-01-01',
            'end_at' => '2025-12-31',
        ]);

        $list2026 = GameList::factory()->create([
            'name' => 'Indies 2026',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $response = $this->get(route('indie-games', ['year' => 2025]));

        $response->assertStatus(200);
        $response->assertSee('Indies 2025');
    });
});

describe('GameList canMarkAsIndie', function () {
    it('returns true for monthly lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
        ]);

        expect($list->canMarkAsIndie())->toBeTrue();
    });

    it('returns true for seasoned lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::SEASONED,
            'is_system' => true,
        ]);

        expect($list->canMarkAsIndie())->toBeTrue();
    });

    it('returns false for indie lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_system' => true,
        ]);

        expect($list->canMarkAsIndie())->toBeFalse();
    });

    it('returns false for highlights lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
        ]);

        expect($list->canMarkAsIndie())->toBeFalse();
    });

    it('returns false for regular lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::REGULAR,
        ]);

        expect($list->canMarkAsIndie())->toBeFalse();
    });
});

describe('Toggle Game Indie', function () {
    it('marks a game as indie with genre', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'is_indie' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-indie', [
                'type' => 'monthly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'indie_genre' => 'metroidvania',
            ]);

        $response->assertJson([
            'success' => true,
            'is_indie' => true,
        ]);

        $pivotRecord = $list->games()->where('game_id', $game->id)->first();
        expect($pivotRecord->pivot->is_indie)->toBe(1);
        expect($pivotRecord->pivot->indie_genre)->toBe('metroidvania');
    });

    it('removes indie status when already marked', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'is_indie' => true,
            'indie_genre' => 'roguelike',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-indie', [
                'type' => 'monthly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]));

        $response->assertJson([
            'success' => true,
            'is_indie' => false,
        ]);

        $pivotRecord = $list->games()->where('game_id', $game->id)->first();
        expect($pivotRecord->pivot->is_indie)->toBe(0);
        expect($pivotRecord->pivot->indie_genre)->toBeNull();
    });

    it('requires genre when marking as indie', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'is_indie' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-indie', [
                'type' => 'monthly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Genre is required when marking as indie.']);
    });

    it('rejects indie toggle for non-monthly/seasoned lists', function () {
        $list = GameList::factory()->create([
            'name' => 'Test Highlights',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, ['order' => 1]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-indie', [
                'type' => 'highlights',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'indie_genre' => 'platformer',
            ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Indie toggle is only available for monthly and seasoned lists.']);
    });

    it('syncs game to yearly indie list when marked', function () {
        $indieList = GameList::factory()->create([
            'name' => 'Indies 2026',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_indie' => false,
            'platforms' => json_encode([6, 130]),
            'release_date' => '2026-01-15',
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-indie', [
                'type' => 'monthly',
                'slug' => $monthlyList->slug,
                'game' => $game->id,
            ]), [
                'indie_genre' => 'metroidvania',
            ]);

        $this->assertDatabaseHas('game_list_game', [
            'game_list_id' => $indieList->id,
            'game_id' => $game->id,
            'indie_genre' => 'metroidvania',
        ]);
    });

    it('does not add duplicate game to yearly indie list', function () {
        $indieList = GameList::factory()->create([
            'name' => 'Indies 2026',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_indie' => false,
        ]);
        $indieList->games()->attach($game->id, [
            'order' => 1,
            'indie_genre' => 'roguelike',
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-indie', [
                'type' => 'monthly',
                'slug' => $monthlyList->slug,
                'game' => $game->id,
            ]), [
                'indie_genre' => 'metroidvania',
            ]);

        expect($indieList->games()->where('game_id', $game->id)->count())->toBe(1);
        expect($indieList->games()->where('game_id', $game->id)->first()->pivot->indie_genre)
            ->toBe('roguelike');
    });

    it('removes game from yearly indie list when indie status is toggled off', function () {
        $indieList = GameList::factory()->create([
            'name' => 'Indies 2026',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_indie' => true,
            'indie_genre' => 'metroidvania',
            'platforms' => json_encode([6, 130]),
            'release_date' => '2026-01-15',
        ]);
        $indieList->games()->attach($game->id, [
            'order' => 1,
            'indie_genre' => 'metroidvania',
        ]);

        expect($indieList->games()->where('game_id', $game->id)->exists())->toBeTrue();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-indie', [
                'type' => 'monthly',
                'slug' => $monthlyList->slug,
                'game' => $game->id,
            ]));

        $response->assertJson([
            'success' => true,
            'is_indie' => false,
        ]);

        expect($indieList->games()->where('game_id', $game->id)->exists())->toBeFalse();
    });
});

describe('Get Game Genres', function () {
    it('returns game genres for modal', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $genre = Genre::factory()->create(['name' => 'Platformer']);
        $game->genres()->attach($genre->id);

        $list->games()->attach($game->id, ['order' => 1]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.system-lists.games.genres', [
                'type' => 'monthly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'genres' => [
                ['id', 'name', 'slug'],
            ],
            'current_indie_genre',
            'is_indie',
        ]);
        $response->assertJsonPath('genres.0.name', 'Platformer');
    });
});

describe('CreateSystemList Command', function () {
    it('creates a yearly indie list for the given year', function () {
        $this->artisan('system-list:create', ['type' => 'indie', 'year' => 2026])
            ->assertSuccessful()
            ->expectsOutputToContain('Created: Indies 2026');

        $this->assertDatabaseHas('game_lists', [
            'name' => 'Indies 2026',
            'list_type' => ListTypeEnum::INDIE_GAMES->value,
            'is_system' => true,
            'is_active' => true,
        ]);
    });

    it('creates a yearly highlights list for the given year', function () {
        $this->artisan('system-list:create', ['type' => 'highlights', 'year' => 2026])
            ->assertSuccessful()
            ->expectsOutputToContain('Created: Highlights 2026');

        $this->assertDatabaseHas('game_lists', [
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS->value,
            'is_system' => true,
            'is_active' => true,
        ]);
    });

    it('fails if an indie list already exists for the year', function () {
        GameList::factory()->create([
            'name' => 'Indies 2026',
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $this->artisan('system-list:create', ['type' => 'indie', 'year' => 2026])
            ->assertFailed();
    });

    it('rejects invalid list types', function () {
        $this->artisan('system-list:create', ['type' => 'invalid', 'year' => 2026])
            ->assertFailed()
            ->expectsOutput("Invalid type 'invalid'. Valid types are: indie, highlights");
    });

    it('rejects invalid years', function () {
        $this->artisan('system-list:create', ['type' => 'indie', 'year' => 1900])
            ->assertFailed()
            ->expectsOutput('Invalid year. Please enter a year between 2000 and 2100.');
    });
});

describe('ListTypeEnum label', function () {
    it('returns Indies for INDIE_GAMES', function () {
        expect(ListTypeEnum::INDIE_GAMES->label())->toBe('Indies');
    });
});

describe('Redirect from old indie-games route', function () {
    it('redirects from /releases/indie-games to /indie-games', function () {
        $response = $this->get('/releases/indie-games');

        $response->assertRedirect('/indie-games');
        $response->assertStatus(301);
    });
});
